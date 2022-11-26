<?php

namespace Cherry\Test\Middleware;

use Medoo\Medoo;
use Cherry\Test\PSR7ObjectProvider;
use Cherry\Test\TestCase;

class SiteLockingMiddlewareTest extends TestCase
{
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

        $request = $provider->createServerRequest('/objects/fake-note-id/details', 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertContains('/login', $response->getHeader('Location'));

        $request = $provider->createServerRequest('/objects/fake-note-id/details', 'GET');
        $request = $request->withHeader('Accept', 'application/activity+json');
        $response = $this->app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertContains('/login', $response->getHeader('Location'));

        $request = $provider->createServerRequest('/tags/tag', 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertContains('/login', $response->getHeader('Location'));

        $request = $provider->createServerRequest('/', 'GET');
        $this->signIn($request);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
