<?php
use Slim\App;
use adrianfalleiro\SlimCLIRunner;
use Cherry\Middleware\SetSessionCookieMiddleware;

return function (App $app) {

    $container = $app->getContainer();

    $app->add(SlimCLIRunner::class);
    $app->addRoutingMiddleware();
    $app->add(SetSessionCookieMiddleware::class);
    $configs = $container->get('configs');
    $debug = $configs['debug'] ?? false;
    if (!$debug) {
        error_reporting(0);
    }
    $app->addErrorMiddleware($debug, $debug, true);
};
