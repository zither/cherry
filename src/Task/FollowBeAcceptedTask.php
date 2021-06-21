<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\RetryException;
use adrianfalleiro\TaskInterface;
use Cherry\Helper\SignRequest;
use Cherry\Helper\Time;
use Godruoyi\Snowflake\Snowflake;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class FollowBeAcceptedTask implements TaskInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function command(array $args)
    {
        $db = $this->container->get(Medoo::class);
        $activityId = $args['activity_id'];
        $activity = $db->get('activities', '*', ['id' => $activityId]);

        $rawActivity = json_decode($activity['raw'], true);
        $profile = $db->get('profiles', ['id', 'inbox'], ['actor' => $rawActivity['actor']]);

        if (empty($activity) || empty($profile)) {
            throw new FailedTaskException('Both activity and profile required');
        }

        $exists = $db->count('following', ['profile_id' => $profile['id']]);
        if ($exists) {
            return;
        }
        $followActivity = $rawActivity['object'];
        $followActivityId = $db->get('activities', ['id'], ['activity_id' => $followActivity['id']]);
        $following = [
            'profile_id' => $profile['id'],
            'status' => 1,
            'follow_activity_id' => $followActivityId,
            'accept_activity_id' => $activityId,
            'created_at' => Time::utc($activity['published']),
        ];
        $db->insert('following', $following);
    }
}