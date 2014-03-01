<?php

/**
 * Silex app
 */
require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__ . '/../data/main.log',
))->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/views',
))->register(new Silex\Provider\SessionServiceProvider(
    // no config
))->register(new Silex\Provider\FormServiceProvider(), array(
    'form.secret' => md5($app['session']->getId())
))->register(new Silex\Provider\TranslationServiceProvider(
    // no config
));

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
            $app->abort(404, "Draft $uuid does not exist.");
        }
    } else {
        // Wrong UUID
        $app->abort(404, "Draft does not exist.");
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
                $message = 'Ouch! Something went terribly wrong...';
        }        
    }
    
    return $app['twig']->render('error.twig', array(
        'code'      => $code, 
        'message'   => $message
    ));
});

$app->run();