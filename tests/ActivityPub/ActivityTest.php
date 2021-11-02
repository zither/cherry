<?php

namespace Cherry\Test\ActivityPub;

use Cherry\ActivityPub\Activity;
use PHPUnit\Framework\TestCase;

class ActivityTest extends TestCase
{
    public function testActorIsAlias()
    {
        $rawActivity = file_get_contents(ROOT . '/tests/data/poll-activity.json');
        $activity = Activity::createFromArray(json_decode($rawActivity, true));
        $this->assertFalse($activity->isActorAlias());

        $rawActivity = file_get_contents(ROOT . '/tests/data/follow-activity-from-zap-slave-server.json');
        $activity = Activity::createFromArray(json_decode($rawActivity, true));
        $this->assertTrue($activity->isActorAlias());

        $rawActivity = file_get_contents(ROOT . '/tests/data/create-activity-from-zap-slave-server.json');
        $activity = Activity::createFromArray(json_decode($rawActivity, true));
        $this->assertNotEmpty($activity->signature);
        $this->assertTrue($activity->isActorAlias());
    }
}
