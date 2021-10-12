<?php
use Slim\App;

return function (App $app) {
    $configs = $app->getContainer()->get('configs');
    date_default_timezone_set($configs['default_time_zone'] ?? 'UTC');
};