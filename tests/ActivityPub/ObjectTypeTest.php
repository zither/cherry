<?php

namespace Cherry\Test\ActivityPub;

use Cherry\ActivityPub\ObjectType;
use PHPStan\Testing\TestCase;

class ObjectTypeTest extends TestCase
{
    public function testCreateFromArrayWithDefaultContext()
    {
        $object = [
            'id' => 'https://cherry.test/outbox/7ff0b3994/object',
            'type' => 'Note',
            'content' => 'Object content.'
        ];
        $objectType = ObjectType::createFromArray($object);
        $objectArray = $objectType->toArray();
        $this->assertArrayHasKey('context', $objectArray);
        $expected = [
            "https://www.w3.org/ns/activitystreams",
            ['sensitive' => 'as:sensitive']
        ];
        $this->assertEquals($expected, $objectArray['context']);
    }
}