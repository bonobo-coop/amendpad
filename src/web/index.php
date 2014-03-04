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
))->register(new Silex\Provider\FormServiceProvider(), array(
    'form.secret' => md5($app['session']->getId())
))->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallbacks' => array('en', 'es', 'ca'), // Default
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

$app['twig'] = $app->share($app->extend('twig', function($twig) use ($locales) {
    $twig->addGlobal('locale', 'en'); // Default
    $twig->addGlobal('locales', $locales);
    $twig->addGlobal('host', $_SERVER['SERVER_NAME']);
    return $twig;
}));

$app->before(function () use ($app, $locales) {
    
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
        $app->abort(500, 'Unable to create draft.');
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
            $app->abort(404, $app['translator']->trans('messages.draft.notfound'));
        }
    } else {
        // Wrong UUID
        $app->abort(404, $app['translator']->trans('messages.draft.notfound'));
    }
    
    // Build form
    $form = $app['form.factory']->createBuilder('form', $draft->exportData())
            ->add('title')
            ->add('body', 'textarea')
            ->add('status', 'choice', array(
                'choices' => array(0 => 'Private', 1 => 'Public')
            ))->getForm();

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

/**
 * Error handler
 */

$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        $message = $e->getMessage();
    } else {
        switch ($code) {
            case 404:
                $message = $e->getMessage();
                break;
            default:
                $message = $app['translator']->trans('messages.system.globalerror');
        }        
    }
    
    return $app['twig']->render('error.twig', array(
        'code'      => $code, 
        'message'   => $message
    ));
});

$app->run();