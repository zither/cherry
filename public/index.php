<?php
use Slim\Factory\AppFactory;
use DI\Container;

define('ROOT', dirname(__DIR__));

require ROOT . '/vendor/autoload.php';
require ROOT . '/src/includes/constants.php';

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

$configs = require ROOT . '/configs/configs.php';
$container->set('configs', $configs);
(require ROOT . '/src/includes/dependencies.php')($app);
(require ROOT . '/src/includes/middlewares.php')($app);
(require ROOT . '/src/includes/settings.php')($app);
(require ROOT . '/src/includes/routes.php')($app);

$app->run();