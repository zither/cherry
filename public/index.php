<?php
use Slim\Factory\AppFactory;
use DI\Container;

define('ROOT', dirname(__DIR__));

require ROOT . '/vendor/autoload.php';

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

(require ROOT . '/src/dependencies.php')($app, $container);
(require ROOT . '/src/middlewares.php')($app, $container);
(require ROOT . '/src/settings.php')($app, $container);
(require ROOT . '/src/routes.php')($app, $container);

$app->run();