<?php
use Slim\App;
use Psr\Container\ContainerInterface;
use adrianfalleiro\SlimCLIRunner;
use Cherry\Middleware\SetSessionCookieMiddleware;

return function (App $app, ContainerInterface $container) {
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
