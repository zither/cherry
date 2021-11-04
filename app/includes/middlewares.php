<?php
use Slim\App;
use adrianfalleiro\SlimCLIRunner;
use Cherry\Middleware\SessionCookieMiddleware;

return function (App $app) {

    $container = $app->getContainer();

    $app->add(SlimCLIRunner::class);
    $app->addRoutingMiddleware();
    $app->add(SessionCookieMiddleware::class);
    $configs = $container->get('configs');
    $debug = $configs['debug'] ?? false;
    if (!$debug) {
        error_reporting(0);
    }
    $app->addErrorMiddleware($debug, $debug, true);
};
