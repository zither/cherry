<?php
use Slim\Factory\AppFactory;
use DI\Container;
use Cherry\CallableResolver;

define('ROOT', dirname(__DIR__));

require ROOT . '/vendor/autoload.php';
require ROOT . '/app/includes/constants.php';

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create(null, null, new CallableResolver($container));

$configs = require ROOT . '/configs/configs.php';
$container->set('configs', $configs);
(require ROOT . '/app/includes/settings.php')($app);
(require ROOT . '/app/includes/dependencies.php')($app);
(require ROOT . '/app/includes/middlewares.php')($app);
(require ROOT . '/app/includes/routes.php')($app);

$app->run();