<?php

namespace Cherry\Test\Task;

use Cherry\Task\FetchProfileByAccountTask;
use donatj\MockWebServer\Response;
use GuzzleHttp\Exception\GuzzleException;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Cherry\Test\Traits\MockServer;
use Cherry\Test\Traits\SetupCherryEnv;

class FetchProfileByAccountTaskTest extends TestCase
{
    use SetupCherryEnv;
    use MockServer;

    public static function setUpBeforeClass(): void
    {
        self::startMockServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopMockerServer();
    }

    public function setUp(): void
    {
        $this->setUpCherryEnv();
    }

    public function tearDown(): void
    {
        $this->tearDownCherryEnv();
    }

    public function testCommandUsingLocalProfile()
    {
        $account = 'dev@cherry.test';
        $args = ['account' => $account];
        $task = new FetchProfileByAccountTask($this->container);
        $profile = $task->command($args);
        $this->assertArrayHasKey('id', $profile);
        $this->assertEquals('1', $profile['id']);
    }

    public function testCommandUsingInvalidHost()
    {
        $this->expectException(GuzzleException::class);
        $account = 'dev@invalid.localhost';
        $args = ['account' => $account];
        $task = new FetchProfileByAccountTask($this->container);
        $task->command($args);
    }

    public function testCommandUsingMockWebFinger()
    {
        $webfinger = <<<JSON
{
  "subject": "acct:relay@neko-relay.com",
  "links": [
    {
      "rel": "self",
      "type": "application/activity+json",
      "href": "%s/actor"
    }
  ]
}
JSON;
        $webfingerPath = '/.well-known/webfinger';
        $body = sprintf($webfinger, self::$server->getServerRoot());
        self::$server->setResponseOfPath($webfingerPath, new Response(
            $body,
            ['Content-Type' => 'application/activity+json'],
        ));

        $actor = file_get_contents(ROOT . '/tests/data/relay-actor.json');
        self::$server->setResponseOfPath('/actor', new Response(
            $actor,
            ['Content-Type' => 'application/activity+json'],
        ));

        $account = 'relay@neko-relay.com';
        $args = [
            'account' => $account,
            'mock_server' => self::$server->getServerRoot() . $webfingerPath,

        ];
        $task = new FetchProfileByAccountTask($this->container);
        $profile = $task->command($args);
        $this->assertArrayHasKey('id', $profile);
        $this->assertNotEmpty($profile['id']);

        $db = $this->container->get(Medoo::class);
        $accountInDB = $db->get('profiles', 'account', ['id' => $profile['id']]);
        $this->assertEquals($account, $accountInDB);
    }

    public function testCommandUsingWebFingerWithEmptyLinks()
    {
        $this->expectExceptionMessage('Links for relay@neko-relay.com not found');
        $webfinger = <<<JSON
{
  "subject": "acct:relay@neko-relay.com",
  "links": []
}
JSON;
        $webfingerPath = '/.well-known/webfinger';
        $body = sprintf($webfinger, self::$server->getServerRoot());
        self::$server->setResponseOfPath($webfingerPath, new Response(
            $body,
            ['Content-Type' => 'application/activity+json'],
        ));

        $account = 'relay@neko-relay.com';
        $args = [
            'account' => $account,
            'mock_server' => self::$server->getServerRoot() . $webfingerPath,

        ];
        $task = new FetchProfileByAccountTask($this->container);
        $task->command($args);
    }

    public function testCommandUsingWebFingerWithoutRelLink()
    {
        $this->expectExceptionMessage('Profile link for relay@neko-relay.com not found');
        $webfinger = <<<JSON
{
  "subject": "acct:relay@neko-relay.com",
  "links": [
    {
      "rel":"http://webfinger.net/rel/profile-page",
      "type":"text/html",
      "href":"https://neko-relay.com"
    }
  ]
}
JSON;
        $webfingerPath = '/.well-known/webfinger';
        $body = sprintf($webfinger, self::$server->getServerRoot());
        self::$server->setResponseOfPath($webfingerPath, new Response(
            $body,
            ['Content-Type' => 'application/activity+json'],
        ));

        $account = 'relay@neko-relay.com';
        $args = [
            'account' => $account,
            'mock_server' => self::$server->getServerRoot() . $webfingerPath,

        ];
        $task = new FetchProfileByAccountTask($this->container);
        $task->command($args);
    }
}
