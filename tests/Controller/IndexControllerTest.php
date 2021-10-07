<?php

namespace Cherry\Test\Controller;

use Cherry\Session\SessionInterface;
use Cherry\Test\PSR7ObjectProvider;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use DI\Container;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface;

class IndexControllerTest extends TestCase
{
    protected $app;
    protected $container;

    public function setUp(): void
    {
        $container = new Container();
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $settings = require ROOT . '/tests/configs/configs_testing.php';
        $container->set('settings', $settings);
        (require ROOT . '/src/includes/dependencies.php')($app);
        (require ROOT . '/src/includes/middlewares.php')($app);
        (require ROOT . '/src/includes/settings.php')($app);
        (require ROOT . '/src/includes/routes.php')($app);

        $this->app = $app;
        $this->container = $container;

        $this->prepareDatabase($settings);
    }

    protected function prepareDatabase(array $settings)
    {
        $database = $settings['database'];
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
            'id' => 1,
            'domain' => $domain,
            'password' => $passwordHash,
            'public_key' => $publicKey,
            'private_key' => $privateKey,
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

    public function tearDown(): void
    {
        $settings = $this->container->get('settings');
        $db = $this->container->get(Medoo::class);
        $db->exec("DROP DATABASE {$settings['database']['name']}");
    }

    public function testCreateServerRequest()
    {
        $data = ['q' => '1'];
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/', 'POST', $data);
        $this->assertEquals($data, $request->getParsedBody());
    }

    public function testBypassCLIRunner()
    {
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/editor');
        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('401', $response->getStatusCode());
    }

    public function testSendFollowWithoutPostParams()
    {
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/following', 'POST');
        $factory = $this->container->get(SessionInterface::class);
        /** @var SessionInterface $session */
        $session = $factory($this->container, $request);
        $session->start();
        $session['is_admin'] = 1;
        $this->app->handle($request);
        $this->assertArrayHasKey('error', $session['MemoFlashMessages']['forNext']);
        $this->assertEquals($session['MemoFlashMessages']['forNext']['error'], ['Account required']);
    }
}