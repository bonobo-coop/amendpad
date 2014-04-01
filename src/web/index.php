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
))->register(new Silex\Provider\ServiceControllerServiceProvider(
    // no config
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
 * Controllers
 */

$db = new App\Db\MongoWrapper('localhost:27017', 'amendpad');

$app['index.controller'] = $app->share(function() use ($app, $db) {
    return new App\Controller\IndexController($app, $db);
});

$app['draft.controller'] = $app->share(function() use ($app, $db) {
    return new App\Controller\DraftController($app, $db);
});

$app['api.controller'] = $app->share(function() use ($app, $db) {
    return new App\Controller\ApiController($app, $db);
});

/**
 * Router
 */

// Home
$app->get('/', 'index.controller:indexAction');

// Draft management
$app->post('/draft', 'draft.controller:createAction');
$app->match('/draft/{uuid}', 'draft.controller:privateAction');
$app->get('/doc/{uuid}', 'draft.controller:publicAction');

// REST API
$app->post('/api/doc/{uuid}/amendment/', 'api.controller:createAction');
$app->get('/api/doc/{uuid}/amendment/', 'api.controller:indexAction');

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