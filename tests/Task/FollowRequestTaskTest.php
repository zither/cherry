<?php

namespace Cherry\Test\Task;

use adrianfalleiro\FailedTaskException;
use Cherry\ActivityPub\Activity;
use Cherry\Task\FollowRequestTask;
use Cherry\Test\TestCase;
use Medoo\Medoo;

class FollowRequestTaskTest extends TestCase
{
    public function testCommandWithInvalidActivityFromZapSlaveServer()
    {
        $this->expectException(FailedTaskException::class);
        $this->expectExceptionMessage('Hosts do not match: id host: zap.dog, actor host: z.fedipen.xyz');

        $rawActivity = file_get_contents(ROOT . '/tests/data/follow-activity-from-zap-slave-server.json');
        $activity = Activity::createFromArray(json_decode($rawActivity, true));
        $data = [
            'activity_id' => $activity->id,
            'profile_id' => 1,
            'object_id' => 0,
            'type' => $activity->type,
            'raw' => $rawActivity,
            'is_local' => 0,
            'is_public' => $activity->isPublic(),
        ];
        $db = $this->container->get(Medoo::class);
        $db->insert('activities', $data);
        $activityId = $db->id();

        $task = new FollowRequestTask($this->container);
        $task->command(['activity_id' => $activityId]);
    }
}
