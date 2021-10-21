<?php
use Slim\App;
use Cherry\Helper\Time;

return function (App $app) {
    $configs = $app->getContainer()->get('configs');
    date_default_timezone_set($configs['default_time_zone'] ?? 'UTC');
    Time::$defaultTimeZone = $configs['default_time_zone'] ?? 'UTC';
};
