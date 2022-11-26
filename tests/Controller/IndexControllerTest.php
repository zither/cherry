<?php

namespace Cherry\Test\Controller;

use Cherry\Session\SessionInterface;
use Cherry\Test\PSR7ObjectProvider;
use Cherry\Test\Traits\SetupCherryEnv;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class IndexControllerTest extends TestCase
{
    use SetupCherryEnv;

    public function setUp(): void
    {
        $this->setUpCherryEnv();
    }

    public function tearDown(): void
    {
        $this->tearDownCherryEnv();
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
        $session = $this->signIn($request);
        $this->app->handle($request);
        $this->assertContains(
            'Account required',
            $this->getNextMessagesByTypeFromSession($session, 'error')
        );
    }

    public function testSendFollowWithNormalAccount()
    {
        $normalAccount = 'dev@cherry.test';
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/following', 'POST', ['account' => $normalAccount]);
        $session = $this->signIn($request);
        $this->app->handle($request);
        $this->assertContains(
            'Follow Request Sent!',
            $this->getNextMessagesByTypeFromSession($session, 'success')
        );
    }

    public function testSendFollowWithNormalAccountThatStartedWithAt()
    {
        $account = '@dev@cherry.test';
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/following', 'POST', ['account' => $account]);
        $session = $this->signIn($request);
        $this->app->handle($request);
        $this->assertContains(
            'Follow Request Sent!',
            $this->getNextMessagesByTypeFromSession($session, 'success')
        );
    }

    public function testSendFollowWithInvalidAccount()
    {
        $account = 'this is not a account';
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/following', 'POST', ['account' => $account]);
        $session =  $this->signIn($request);
        $this->app->handle($request);
        $this->assertContains(
            'Invalid Account',
            $this->getNextMessagesByTypeFromSession($session, 'error')
        );
    }

    public function testSendFollowWithURLAccount()
    {
        $account = 'https://localhost/actor';
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/following', 'POST', ['account' => $account]);
        $session =  $this->signIn($request);
        $this->app->handle($request);
        $this->assertContains(
            'Follow Request Sent!',
            $this->getNextMessagesByTypeFromSession($session, 'success')
        );
        $db = $this->container->get(Medoo::class);
        $task = $db->get('tasks', '*', ['task' => 'FollowTask']);
        $this->assertNotEmpty($task);
        $params = json_decode($task['params'], true);
        $this->assertArrayHasKey('is_url', $params);
        $this->assertEquals(true, $params['is_url']);
    }

    public function testSendFollowWithURLAccountThatDoesNotContainProtocol()
    {
        $account = 'localhost/actor';
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/following', 'POST', ['account' => $account]);
        $session =  $this->signIn($request);
        $this->app->handle($request);
        $this->assertContains(
            'Invalid Account',
            $this->getNextMessagesByTypeFromSession($session, 'error')
        );
    }

    public function testCreatePostWithMentionTag()
    {
        $content = 'Mention: @dev@cherry.test .';
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/editor', 'POST', ['content' => $content]);
        $session =  $this->signIn($request);
        $this->app->handle($request);

        $db = $this->container->get(Medoo::class);
        $rawActivity = $db->get('activities', 'raw', ['LIMIT' => 1]);
        $this->assertNotEmpty($rawActivity);

        $activity = json_decode($rawActivity, true);
        $this->assertArrayHasKey('object', $activity);

        $object = $activity['object'];
        $this->assertArrayHasKey('tag', $object);
        $expectedTags = [
            [
                'type' => 'Mention',
                'href' => 'https://cherry.test',
                'name' => '@dev@cherry.test'
            ]
        ];
        $this->assertEquals($expectedTags, $object['tag']);
        $this->assertArrayHasKey('cc', $object);
        $this->assertContains('https://cherry.test', $object['cc']);
    }

    public function testGetSettings()
    {
        $settings = $this->container->make('settings');
        $this->assertArrayHasKey('domain', $settings);
        $this->assertArrayHasKey('password', $settings);
        $this->assertArrayHasKey('public_key', $settings);
        $this->assertArrayHasKey('private_key', $settings);
        $this->assertEquals('cherry.test', $settings['domain']);
    }

    public function testTags()
    {
        $content = 'Tag: #测试标签 #cherry';
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/editor', 'POST', ['content' => $content]);
        $this->signIn($request);
        $this->app->handle($request);

        $db = $this->container->get(Medoo::class);
        $unicodeTagCount =$db->count('tags', ['term' => '测试标签']);
        $totalCount  = $db->count('tags');
        $this->assertEquals(1, $unicodeTagCount);
        $this->assertEquals(2, $totalCount);

        $request = $provider->createServerRequest('/tags/测试标签');
        $this->signIn($request);
        $response = $this->app->handle($request);
        $this->assertStringContainsString('#测试标签', (string)$response->getBody());
    }

    public function testTagsWithPrivateTagAfterLoggingIn()
    {
        $content = 'Tag: #已登录';
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/editor', 'POST', ['content' => $content, 'scope' => 4]);
        $session = $this->signIn($request);
        $sessionName = $session->getName();
        $sessionId = $session->getId();
        $request = $request->withCookieParams([$sessionName => $sessionId]);
        $this->app->handle($request);

        $db = $this->container->get(Medoo::class);
        $count =$db->count('tags', ['term' => '已登录']);
        $this->assertEquals(1, $count);

        $request = $provider->createServerRequest('/tags/已登录');
        $request = $request->withCookieParams([$sessionName => $sessionId]);
        $response = $this->app->handle($request);
        $this->assertStringContainsString('#已登录', (string)$response->getBody());
    }

    public function testTagsWithPrivateTagWithoutLoggingIn()
    {
        $content = 'Tag: #已登录';
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/editor', 'POST', ['content' => $content, 'scope' => 4]);
        $this->signIn($request);
        $this->app->handle($request);
        $db = $this->container->get(Medoo::class);
        $count =$db->count('tags', ['term' => '已登录']);
        $this->assertEquals(1, $count);

        $request = $provider->createServerRequest('/tags/已登录');
        $response = $this->app->handle($request);
        $this->assertStringNotContainsString('#已登录', (string)$response->getBody());
    }

    public function testVerifyPasswordWithInvalidPassword()
    {
        $provider = new PSR7ObjectProvider();
        $invalidPassword = 'invalid-password';
        $request = $provider->createServerRequest('/login', 'POST', ['password' => $invalidPassword]);
        $session = $this->container->make(SessionInterface::class, ['request' => $request]);
        $sessionName = $session->getName();
        $sessionId = $session->getId();
        $request = $request->withCookieParams([$sessionName => $sessionId]);
        $this->app->handle($request);
        $this->assertContains('Invalid password', $this->getNextMessagesByTypeFromSession($session, 'error'));
        $settings = $this->container->make('settings');
        $this->assertEquals('1', $settings['login_retry']);

        $this->app->handle($request);
        $settings = $this->container->make('settings');
        $this->assertEquals('2', $settings['login_retry']);

        $session = $this->container->make(SessionInterface::class, ['request' => $request]);
        $this->app->handle($request);
        $settings = $this->container->make('settings');
        $this->assertContains(
            'Too many failed login attempts',
            $this->getNextMessagesByTypeFromSession($session, 'error')
        );$this->assertEquals('0', $settings['login_retry']);

        $session = $this->container->make(SessionInterface::class, ['request' => $request]);
        $this->app->handle($request);
        $this->assertContains(
            'Login Denied',
            $this->getNextMessagesByTypeFromSession($session, 'error')
        );

        $db = $this->container->get(Medoo::class);
        $db->update('settings', ['v' => 0], ['k' => 'deny_login_until', 'cat' => 'system']);
        $session = $this->container->make(SessionInterface::class, ['request' => $request]);
        $this->app->handle($request);
        $this->assertContains('Invalid password', $this->getNextMessagesByTypeFromSession($session, 'error'));
        $settings = $this->container->make('settings');
        $this->assertEquals('1', $settings['login_retry']);
    }

    protected function getNextMessagesByTypeFromSession(SessionInterface $session, string $type): array
    {
        return $session['MemoFlashMessages']['forNext'][$type] ?? [];
    }

    protected function signIn(ServerRequestInterface $request): SessionInterface
    {
        $session = $this->container->make(SessionInterface::class, ['request' => $request]);
        $session['is_admin'] = 1;
        return $session;
    }
}