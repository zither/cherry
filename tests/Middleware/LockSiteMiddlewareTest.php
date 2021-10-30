<?php

namespace Cherry\Test\Middleware;

use Cherry\Test\PSR7ObjectProvider;
use Cherry\Test\Traits\SetupCherryEnv;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;

class LockSiteMiddlewareTest extends TestCase
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

    public function testHandleWithLockSitePreference0()
    {
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/', 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandleWithLockSitePreference1()
    {
        $db = $this->container->get(Medoo::class);
        $db->update('settings', ['v' => 1], ['k' => 'lock_site']);
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/', 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertContains('/login', $response->getHeader('Location'));

        $request = $provider->createServerRequest('/notes/fake-note-id', 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertContains('/login', $response->getHeader('Location'));

        $request = $provider->createServerRequest('/tags/tag', 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertContains('/login', $response->getHeader('Location'));
    }
}