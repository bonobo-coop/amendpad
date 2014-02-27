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
));

/**
 * Router + Controller
 */
$app->get('/', function () use ($app) {
    return $app['twig']->render('home.twig');
});

$app->run();