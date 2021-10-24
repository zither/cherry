<?php

namespace Cherry\Test\Traits;

use Medoo\Medoo;
use Slim\App;
use DI\Container;
use Slim\Factory\AppFactory;

trait SetupCherryEnv
{
    /**
     * @var App
     */
    protected $app;

    /**
     * @var Container
     */
    protected $container;


    public function setUpCherryEnv(): void
    {
        $container = new Container();
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $configs = require ROOT . '/tests/configs/configs_testing.php';
        $container->set('configs', $configs);
        (require ROOT . '/app/includes/settings.php')($app);
        (require ROOT . '/app/includes/dependencies.php')($app);
        (require ROOT . '/app/includes/middlewares.php')($app);
        (require ROOT . '/app/includes/routes.php')($app);

        $this->app = $app;
        $this->container = $container;

        $this->prepareDatabase($configs);
    }

    protected function prepareDatabase(array $configs)
    {
        $database = $configs['database'];
        $pdo = new \PDO("mysql:host={$database['host']}",$database['user'], $database['password']);
        $pdo->exec("DROP DATABASE {$database['name']}");
        $pdo->exec("CREATE DATABASE {$database['name']}");
        $pdo->exec("USE {$database['name']}");
        $sql = file_get_contents(ROOT . '/data/cherry.sql');
        $pdo->exec($sql);
        unset($pdo);

        $publicKey = file_get_contents(ROOT . '/tests/configs/public.pem');
        $privateKey = file_get_contents(ROOT . '/tests/configs/private.pem');
        $domain = 'cherry.test';
        $passwordHash = password_hash('123456', PASSWORD_DEFAULT);
        $name = 'dev';
        $preferredName = $name;
        $avatar = '';
        $summary = '';

        $db = $this->container->get(Medoo::class);
        $db->insert('settings', [
            ['cat' => 'system', 'k' => 'domain', 'v' => $domain],
            ['cat' => 'system', 'k' => 'password', 'v' => $passwordHash],
            ['cat' => 'system', 'k' => 'public_key', 'v' => $publicKey],
            ['cat' => 'system', 'k' => 'private_key', 'v' => $privateKey],
            ['cat' => 'system', 'k' => 'login_retry', 'v' => 0],
            ['cat' => 'system', 'k' => 'deny_login_until', 'v' => 0],
        ]);
        $profile = [
            'id' => 1,
            'actor' => "https://$domain",
            'name' => $name,
            'preferred_name' => $preferredName,
            'account' => "$preferredName@$domain",
            'url' => "https://$domain",
            'avatar' => $avatar,
            'summary' => $summary,
            'inbox' => "https://$domain/inbox",
            'outbox' => "https://$domain/outbox",
            'following' => "https://$domain/following",
            'followers' => "https://$domain/followers",
            'featured' => "https://$domain/featured",
            'shared_inbox' => "https://$domain/inbox",
            'public_key' => $publicKey,
        ];
        $db->insert('profiles', $profile);
    }

    public function tearDownCherryEnv(): void
    {
        $configs = $this->container->get('configs');
        $db = $this->container->get(Medoo::class);
        $db->exec("DROP DATABASE {$configs['database']['name']}");
    }
}