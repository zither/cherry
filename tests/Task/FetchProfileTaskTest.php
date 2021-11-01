<?php

namespace Cherry\Test\Task;

use adrianfalleiro\FailedTaskException;
use Cherry\Task\FetchProfileTask;
use donatj\MockWebServer\Response;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Cherry\Test\Traits\MockServer;
use Cherry\Test\Traits\SetupCherryEnv;

class FetchProfileTaskTest extends TestCase
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

    public function testCommand()
    {
        $person = file_get_contents(ROOT . '/tests/data/actor.json');
        $localProfile = json_decode($person, true);
        $path = '/actor';
        $actor = self::$server->getServerRoot() . $path;
        self::$server->setResponseOfPath($path, new Response(
            $person,
            ['Content-Type' => 'application/activity+json'],
        ));
        $task = new FetchProfileTask($this->container);
        $profile = $task->command(['actor' => $actor]);
        $this->assertIsArray($profile);
        $this->assertArrayHasKey('id', $profile);

        $db = $this->container->get(Medoo::class);
        $profileInDB = $db->get('profiles', '*', ['id' => $profile['id']]);

        $this->assertEquals($localProfile['id'], $profileInDB['actor']);
    }

    public function testCommandInvalidActor()
    {
        $this->expectException(FailedTaskException::class);
        $path = '/invalid-actor';
        $actor = self::$server->getServerRoot() . $path;
        self::$server->setResponseOfPath($path, new Response(
            '',
            ['Content-Type' => 'application/activity+json'],
        ));
        $task = new FetchProfileTask($this->container);
        $task->command(['actor' => $actor]);
    }

    public function testCommandWithRelayActor()
    {
        $person = file_get_contents(ROOT . '/tests/data/relay-actor.json');
        $localProfile = json_decode($person, true);
        $path = '/actor';
        $actor = self::$server->getServerRoot() . $path;
        self::$server->setResponseOfPath($path, new Response(
            $person,
            ['Content-Type' => 'application/activity+json'],
        ));
        $task = new FetchProfileTask($this->container);
        $profile = $task->command(['actor' => $actor]);
        $this->assertIsArray($profile);
        $this->assertArrayHasKey('id', $profile);

        $db = $this->container->get(Medoo::class);
        $profileInDB = $db->get('profiles', '*', ['id' => $profile['id']]);

        $this->assertEquals($localProfile['id'], $profileInDB['actor']);
        $this->assertEquals('Group', $profileInDB['type']);
    }

    public function testCommandWithAliases()
    {
        $person = file_get_contents(ROOT . '/tests/data/profile-from-zap-slave-server.json');
        $path = '/actor';
        $actor = self::$server->getServerRoot() . $path;
        self::$server->setResponseOfPath($path, new Response(
            $person,
            ['Content-Type' => 'application/activity+json'],
        ));
        $task = new FetchProfileTask($this->container);
        $profile = $task->command(['actor' => $actor]);
        $this->assertIsArray($profile);
        $this->assertArrayHasKey('id', $profile);

        $db = $this->container->get(Medoo::class);
        $actorAliases = $db->select('actor_aliases', '*', ['profile_id' => $profile['id']]);
        $this->assertNotEmpty($actorAliases);

        $aliases = [];
        foreach ($actorAliases as $v) {
            $aliases[] = $v['alias'];
        }
        $expectedAliases = [
            "https://freetobe.social/channel/asherpen",
            "https://onlinelutherans.com/channel/asherpen",
            "https://z.fedipen.xyz/channel/asherpen"
        ];
        $this->assertEquals($expectedAliases, $aliases);
    }
}
