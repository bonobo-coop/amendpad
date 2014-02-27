<?php

/**
 * Silex app
 */
require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__ . '/../data/main.log',
));

/**
 * Router + Controller
 */
$app->get('/', function () use ($app) {
    return "Hello World";
});

$app->run();