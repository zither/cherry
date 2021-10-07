<?php
use Slim\App;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use adrianfalleiro\SlimCLIRunner;
use Medoo\Medoo;
use League\Plates\Engine;
use Godruoyi\Snowflake\Snowflake;
use Cherry\Session\MedooSession;
use Cherry\Session\SessionInterface;
use Cherry\Markdown;
use Cherry\Helper\SignRequest;

if (!function_exists('short')) {
    function short(string $class)
    {
        $reflection = new ReflectionClass($class);
        return $reflection->getShortName();
    }
}

return function (App $app) {

    $container = $app->getContainer();

    // CLI Tasks
    $container->set('commands', function() {
        $tasks = [
            \Cherry\Task\CronTask::class,
            \Cherry\Task\FollowRequestTask::class,
            \Cherry\Task\CreateRequestTask::class,
            \Cherry\Task\AnnounceRequestTask::class,
            \Cherry\Task\UpdateRequestTask::class,
            \Cherry\Task\AcceptFollowTask::class,
            \Cherry\Task\RejectFollowTask::class,
            \Cherry\Task\FollowBeAcceptedTask::class,
            \Cherry\Task\FollowBeRejectedTask::class,
            \Cherry\Task\FollowTask::class,
            \Cherry\Task\DeleteRemoteNoteTask::class,
            \Cherry\Task\DeliverActivityTask::class,
            \Cherry\Task\PushActivityTask::class,
            \Cherry\Task\DeleteActivityTask::class,
            \Cherry\Task\FetchProfileTask::class,
            \Cherry\Task\RemoteLikeTask::class,
            \Cherry\Task\FetchObjectTask::class,
            \Cherry\Task\RemoteUndoTask::class,
            \Cherry\Task\LocalInteractiveTask::class,
            \Cherry\Task\LocalUndoTask::class,
            \Cherry\Task\RemoteDeleteTask::class,
            \Cherry\Task\LocalUpdateProfileTask::class,
            \Cherry\Task\Cron\DeleteExpiredSessionsTask::class,
        ];
        $commands = [];
        foreach ($tasks as $v) {
            $commands[short($v)] = $v;
        }
        return $commands;
    });

    // CLI Runner
    $container->set(SlimCLIRunner::class, function(ContainerInterface $container) {
        $args = [];
        if (PHP_SAPI === 'cli') {
            global $argv;
            $args = $argv;
        }
        return new SlimCLIRunner($container, $args);
    });

    // Database
    $container->set(Medoo::class, function(ContainerInterface $container) {
        $settings = $container->get('settings');
        return new Medoo([
            'database_type' => $settings['database']['type'],
            'database_name' => $settings['database']['name'],
            'server' => $settings['database']['host'],
            'username' => $settings['database']['user'],
            'password' => $settings['database']['password'],
            'port' => 3306,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'option' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false
            ],
            'command' => ["SET time_zone='+8:00'"],
        ]);
    });

    // Template
    $container->set(Engine::class, function() {
        return new Engine(ROOT . '/src/templates');
    });

    // RouterCollector
    $container->set('router', $app->getRouteCollector());

    // ID generator
    $container->set(Snowflake::class, function () {
        return new Snowflake();
    });

    // Markdown parser
    $container->set(Markdown::class, function() {
        return new Markdown();
    });

    // Session factory
    $container->set(SessionInterface::class, function() {
        return function (ContainerInterface $container, ServerRequestInterface $request) {
            $db = $container->get(Medoo::class);
            $session = new MedooSession($db);
            $cookies = $request->getCookieParams();
            if (isset($cookies[$session->getName()])) {
                $session->setId($cookies[$session->getName()]);
                $session->start();
            }
            $container->set('session', $session);
            return $session;
        };
    });

    // Session factory
    $container->set(SignRequest::class, function(ContainerInterface $container) {
        $db = $container->get(Medoo::class);
        $settings = $db->get('settings', '*', ['id' => 1]);
        $helper = new SignRequest();
        $helper->withKey($settings['public_key'], $settings['private_key']);
        $helper->withDomain($settings['domain']);
        return $helper;
    });
};