<?php

/**
 * Silex app
 */
require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;

// Extensions

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__ . '/../data/main.log',
))->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../views',
))->register(new Silex\Provider\SessionServiceProvider(
    // no config
))->register(new Silex\Provider\ValidatorServiceProvider(
    // no config
))->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallbacks' => array('en', 'es', 'ca'), // Default
))->register(new Silex\Provider\FormServiceProvider(), array(
    'form.secret' => md5($app['session']->getId())
));

// Multilanguage (session dependency)
        
$locales = array(
    'ca' => 'Català',
    'es' => 'Castellano', 
    'en' => 'English', 
);

$app['translator'] = $app->share($app->extend('translator', function($translator) use ($locales) {
    // Load YAML translation files
    $translator->addLoader('yaml', new Symfony\Component\Translation\Loader\YamlFileLoader());
    foreach (array_keys($locales) as $code) {
        $translator->addResource('yaml', __DIR__ . '/../locales/' . $code . '.yml', $code);
    }
    return $translator;
}));

// Twig environment

$app['twig'] = $app->share($app->extend('twig', function($twig) use ($app, $locales) {
    $twig->addGlobal('locale', 'en'); // Default
    $twig->addGlobal('locales', $locales);
    $twig->addGlobal('host', $_SERVER['SERVER_NAME']);
    $twig->addGlobal('csrf_token', $app['form.csrf_provider']->generateCsrfToken('form'));
    return $twig;
}));

$app->before(function () use ($app, $locales) {

    // Allow JSON content type
    
    if (0 === strpos($app['request']->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($app['request']->getContent(), true);
        $app['request']->request->replace(is_array($data) ? $data : array());
    }
    
    // Set user language!
    
    $locale = $app['request']->get('lang');
    
    if ($locale && in_array($locale, array_keys($locales))) {
        // Valid query param
        $app['locale'] = $locale;
        $app['session']->set('locale', $locale);
    } else {
        // Session or fallback
        $app['locale'] = $app['session']->get('locale') ?: 'en';
    }
    
    $app['translator'] = $app->share($app->extend('translator', function($translator, $app) {
        $translator->setLocale($app['locale']);
        return $translator;
    }));
    
    $app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
        $twig->addGlobal('locale', $app['locale']);
        return $twig;
    }));
});

/**
 * Dependency injector
 */
$db = new App\Db\MongoWrapper('localhost:27017', 'amendpad');

/**
 * Router + Controller
 */
$app->get('/', function () use ($app) {
    $form = $app['form.factory']->createBuilder('form')->getForm();
    return $app['twig']->render('home.twig', array(
        'form'  => $form->createView()
    ));
});

$app->post('/draft', function () use ($app, $db) {
    // Create case
    $draft = new App\Entity\Draft();
    $draft->privateKey = App\Db\UUID::v4();
    $draft->publicKey  = App\Db\UUID::v4();
    
    $success = $db->create('drafts', $draft->exportData(), array(
        'privateKey', 
        'publicKey'
    ));
    
    if ($success) {
        return $app->redirect('/draft/' . $draft->privateKey);        
    } else {
        $app->abort(500, $app['translator']->trans('messages.node.notcreated'));
    }
});

$app->match('/draft/{uuid}', function ($uuid) use ($app, $db) {
    // Load document
    $draft = new App\Entity\Draft();
    $uuid = $app->escape($uuid);
    
    if (App\Db\UUID::isValid($uuid)) {
        $doc = $db->findOne('drafts', array('privateKey' => $uuid));
        if ($doc) {
            $draft->importData($doc);
        } else {
            // Document not found
            $app->abort(404, $app['translator']->trans('messages.node.notfound'));
        }
    } else {
        // Wrong UUID
        $app->abort(404, $app['translator']->trans('messages.node.notfound'));
    }
    
    // Build form
    $form = $app['form.factory']->createBuilder('form', $draft->exportData())
        ->add('title')
        ->add('body', 'textarea')
        ->getForm();

    $form->handleRequest($app['request']);
    
    if ($form->isValid()) {
        // Update draft
        $draft->importData($form->getData());
        $db->update('drafts', $draft->exportData());
    }
    
    return $app['twig']->render('draft.twig', array(
        'draft' => $draft,
        'form'  => $form->createView()
    ));
});

$app->get('/doc/{uuid}', function ($uuid) use ($app, $db) {
    // Load document
    $draft = new App\Entity\Draft();
    $uuid = $app->escape($uuid);
    
    if (App\Db\UUID::isValid($uuid)) {
        $doc = $db->findOne('drafts', array('publicKey' => $uuid));
        if ($doc) {
            $draft->importData($doc);
            if ($draft->status !== 1) {
                // Document not published
                $app->abort(403, $app['translator']->trans('messages.node.notpublished'));
            }
        } else {
            // Document not found
            $app->abort(404, $app['translator']->trans('messages.node.notfound'));
        }
    } else {
        // Wrong UUID
        $app->abort(404, $app['translator']->trans('messages.node.notfound'));
    }
    
    return $app['twig']->render('doc.twig', array(
        'draft' => $draft
    ));
});

/* REST API */

use Symfony\Component\Validator\Constraints as Assert;

$app->post('/api/doc/{uuid}/amendment/', function ($uuid) use ($app, $db) {
    // Use form to validate data
    $form = $app['form.factory']->createBuilder()
        ->add('tid', 'text', array(
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('body', 'textarea', array(
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('reason', 'textarea', array(
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('uid', 'text', array(
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('addition', 'text', array(
            'constraints' => array(new Assert\NotBlank())
        ))
        ->getForm();
    
    // Simulate form request structure (adapter pattern)
    $app['request']->request->add(array(
        'form' => $app['request']->request->all()
    ));
    $form->handleRequest($app['request']);
    
    if ($form->isValid()) {
        // Map data
        $data = $form->getData();
        $data['addition'] = (boolean)$data['addition'];        
        // Build amendment
        $amendment = new App\Entity\Amendment($data);
        $amendment->status = App\Entity\Amendment::STATUS_PENDING;
        // Save amendment
        if ($db->create('draft_' . $uuid, $amendment->exportData())) {
            return $app->json(array('success' => true));
        }
    } else {
        $errors = array();
        foreach ($form->all() as $name => $child) {
            if (count($child->getErrors())) {
                $errors[$name] = $child->getErrorsAsString();
            }
        }
        $app->abort(400, json_encode($errors));
    }
    
    $app->abort(500, $app['translator']->trans('messages.node.notcreated'));
});

/**
 * Error handler
 */

$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        $message = $e->getMessage();
    } else {
        switch ($code) {
            case 400: // Validation
            case 403: // Forbidden
            case 404: // Not found
                $message = $e->getMessage();
                break;
            default:
                $message = $app['translator']->trans('messages.system.globalerror');
        }        
    }
    
    $params = array(
        'code'      => $code,
        'message'   => $message
    );
    
    if (0 === strpos($app['request']->headers->get('Content-Type'), 'application/json')) {
        // HACK Try to decode (errors case)
        $decoded = json_decode($message);
        if ($decoded) {
            $params['message'] = $app['translator']->trans('messages.system.detectederrors');
        }
        // JSON
        return $app->json(array(
            'success'   => false,
            'exception' => $params,
            'errors' => $decoded ?: array()
        ));
    } else {
        // HTML
        return $app['twig']->render('error.twig', $params);
    }
});

$app->run();