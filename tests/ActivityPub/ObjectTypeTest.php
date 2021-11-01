<?php

namespace Cherry\Test\ActivityPub;

use Cherry\ActivityPub\Activity;
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
        $this->assertArrayHasKey('@context', $objectArray);
        $expected = [
            "https://www.w3.org/ns/activitystreams",
            ['sensitive' => 'as:sensitive']
        ];
        $this->assertEquals($expected, $objectArray['@context']);
    }

    public function testIsPublic()
    {
        $rawActivity = file_get_contents(ROOT . '/tests/data/poll-activity.json');
        $activity = Activity::createFromArray(json_decode($rawActivity, true));
        $this->assertTrue($activity->isPublic());
        $object = ObjectType::createFromArray($activity->object);
        $this->assertTrue($object->isPublic());

        $objectType = new ObjectType();
        $this->assertFalse($objectType->isPublic());
        $audiences = [];
        $this->assertFalse($objectType->isPublic($audiences));
        $audiences = [
            null,
            "https://cherry.test",
            ["https://o3o.ca/users/yue/followers"],
            [],
            [ObjectType::PUBLIC_COLLECTION],
        ];
        $this->assertTrue($objectType->isPublic($audiences));
    }
}
