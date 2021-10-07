<?php
use Slim\App;
use adrianfalleiro\SlimCLIRunner;
use Cherry\Middleware\SetSessionCookieMiddleware;

return function (App $app) {

    $container = $app->getContainer();

    $app->add(SlimCLIRunner::class);
    $app->addRoutingMiddleware();
    $app->add(SetSessionCookieMiddleware::class);
    $settings = $container->get('settings');
    $debug = $settings['debug'] ?? false;
    if (!$debug) {
        error_reporting(0);
    }
    $app->addErrorMiddleware($debug, $debug, true);
};
