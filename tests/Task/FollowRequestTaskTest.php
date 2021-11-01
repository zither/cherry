<?php

namespace Cherry\Test\Task;

use Cherry\ActivityPub\Activity;
use Cherry\Task\FollowRequestTask;
use Cherry\Test\TestCase;
use Medoo\Medoo;

class FollowRequestTaskTest extends TestCase
{

    public function testCommandWithAliasActivityFromZapSlaveServer()
    {
        $rawActivityContent = file_get_contents(ROOT . '/tests/data/follow-activity-from-zap-slave-server.json');
        $rawActivity = json_decode($rawActivityContent, true);
        $activity = Activity::createFromArray($rawActivity);
        $data = [
            'activity_id' => $activity->id,
            'profile_id' => 1,
            'object_id' => 0,
            'type' => $activity->type,
            'raw' => $rawActivityContent,
            'is_local' => 0,
            'is_public' => $activity->isPublic(),
        ];
        $db = $this->container->get(Medoo::class);
        $db->insert('activities', $data);
        $activityId = $db->id();

        $task = new FollowRequestTask($this->container);
        $task->command(['activity_id' => $activityId]);

        $actor = $rawActivity['signature']['creator'];
        $profile = $db->get('profiles', '*', ['actor' => $actor]);
        $this->assertNotEmpty($profile);

        $notification = $db->get('notifications', '*', ['actor' => $actor, 'profile_id' => $profile['id']]);
        $this->assertNotEmpty($notification);
    }
}
