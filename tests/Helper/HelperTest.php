<?php

namespace Cherry\Test\Helper;

use Cherry\Helper\Helper;
use Cherry\Test\PSR7ObjectProvider;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    public function testIsApi()
    {
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/', 'GET');
        $this->assertFalse(Helper::isApi($request));

        $request = $request->withHeader('Accept', 'application/activity+json');
        $this->assertTrue(Helper::isApi($request));
    }
}