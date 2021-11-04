<?php

namespace Cherry\Test\ActivityPub;

use Cherry\ActivityPub\Context;
use PHPUnit\Framework\TestCase;

class ContextTest extends TestCase
{
    public function testGet()
    {
        $this->assertEmpty(Context::get(0));
        $this->assertEquals(
            Context::$contexts[Context::OPTION_ACTIVITY_STREAMS],
            Context::get(Context::OPTION_ACTIVITY_STREAMS)
        );
        $this->assertEquals(
            [Context::$contexts[Context::OPTION_SECURITY_V1]],
            Context::get(Context::OPTION_SECURITY_V1)
        );
        $expected = [
            "https://www.w3.org/ns/activitystreams",
            "https://w3id.org/security/v1",
            ['sensitive' => 'as:sensitive']
        ];
        $this->assertEquals($expected, Context::get(Context::OPTION_ACTIVITY_STREAMS|Context::OPTION_SECURITY_V1));
    }

    public function testSet()
    {
        $data = ['id' => 1];
        $expected = [
            '@context' => ["https://w3id.org/security/v1"],
            'id' => 1,
        ];
        $this->assertEquals($expected, Context::set($data, Context::OPTION_SECURITY_V1));

        $expected = [
            '@context' => [
                "https://www.w3.org/ns/activitystreams",
                "https://w3id.org/security/v1",
                ['sensitive' => 'as:sensitive']
            ],
            'id' => 1,
        ];
        $this->assertEquals($expected, Context::set($data, Context::OPTION_SECURITY_V1 | Context::OPTION_ACTIVITY_STREAMS));
    }
}
