<?php
use Slim\App;
use Psr\Container\ContainerInterface;

return function (App $app, ContainerInterface $container) {
    date_default_timezone_set('Asia/Shanghai');
};