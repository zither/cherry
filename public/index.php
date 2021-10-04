<?php
use Slim\Factory\AppFactory;
use DI\Container;

define('ROOT', dirname(__DIR__));
define('CHERRY_VERSION', "0.1.0");
define('CHERRY_REPOSITORY', 'https://github.com/zither/cherry');

require ROOT . '/vendor/autoload.php';

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

(require ROOT . '/src/dependencies.php')($app, $container);
(require ROOT . '/src/middlewares.php')($app, $container);
(require ROOT . '/src/settings.php')($app, $container);
(require ROOT . '/src/routes.php')($app, $container);

$app->run();