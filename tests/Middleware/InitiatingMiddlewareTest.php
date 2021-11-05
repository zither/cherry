<?php

namespace Cherry\Test\Middleware;

use Medoo\Medoo;
use Cherry\Test\PSR7ObjectProvider;
use Cherry\Test\TestCase;

class InitiatingMiddlewareTest extends TestCase
{
    public function testHandleBeforeInitiating()
    {
        $db = $this->container->get(Medoo::class);
        $db->delete('settings', ['k' => 'domain']);

        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/', 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $location = $response->getHeaderLine('Location');
        $this->assertEquals('/init', $location);

        $request = $provider->createServerRequest('/init', 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandleAfterInitiating()
    {
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/', 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $request = $provider->createServerRequest('/init', 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $location = $response->getHeaderLine('Location');
        $this->assertEquals('/', $location);
    }
}
