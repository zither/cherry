<?php

namespace Cherry\Test\Controller;

use Cherry\Session\SessionInterface;
use Cherry\Test\PSR7ObjectProvider;
use Cherry\Test\Traits\SetupCherryEnv;
use Medoo\Medoo;
use phpDocumentor\Reflection\DocBlock\Tags\Reference\Url;
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
        $request = $provider->createServerRequest('/notes', 'POST', ['content' => $content]);
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
    }

    protected function getNextMessagesByTypeFromSession(SessionInterface $session, string $type): array
    {
        return $session['MemoFlashMessages']['forNext'][$type] ?? [];
    }

    protected function signIn(ServerRequestInterface $request): SessionInterface
    {
        $factory = $this->container->get(SessionInterface::class);
        /** @var SessionInterface $session */
        $session = $factory($this->container, $request);
        $session->start();
        $session['is_admin'] = 1;
        return $session;
    }
}