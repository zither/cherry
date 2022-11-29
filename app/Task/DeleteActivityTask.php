<?php

namespace Cherry\Task;

use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\Activity;
use Cherry\ActivityPub\ActivityPub;
use Cherry\ActivityPub\Context;
use Cherry\Helper\SignRequest;
use Cherry\Helper\Time;
use Godruoyi\Snowflake\Snowflake;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class DeleteActivityTask implements TaskInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function command(array $args)
    {
        $activityId = $args['activity_id'];
        $db = $this->container->get(Medoo::class);
        $activityData = $db->get('activities', '*', ['id' => $activityId]);
        $originActivity = json_decode($activityData['raw'], true);
        $object = $originActivity['object'];
        $tombstone = [
            'id' => $object['id'],
            'type' => ActivityPub::TOMBSTONE,
            'atomUri' => $object['id'],
        ];
        $settings = $this->container->make('settings', ['keys' => ['domain']]);
        $adminProfile = $db->get('profiles', ['id', 'outbox', 'actor'], ['id' =>  CHERRY_ADMIN_PROFILE_ID]);
        $snowflake = $this->container->get(Snowflake::class);
        $publicId = $snowflake->id();
        $rawActivity = [
            'id' => "https://{$settings['domain']}/activities/{$publicId}",
            'type' => ActivityPub::DELETE,
            'actor' => $adminProfile['actor'],
            'object' => $tombstone,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        ];
        $rawActivity = Context::set($rawActivity, Context::OPTION_ACTIVITY_STREAMS | Context::OPTION_SECURITY_V1);

        $helper = $this->container->get(SignRequest::class);
        $rawActivity['signature'] = $helper->createLdSignature($rawActivity);
        $activity = [
            'activity_id' => $rawActivity['id'],
            'type' => ActivityPub::DELETE,
            'raw' => json_encode($rawActivity, JSON_UNESCAPED_SLASHES),
            'published' => TIme::getLocalTime(),
            'is_local' => 1,
        ];
        $db->insert('activities', $activity);
        $id = $db->id();
        $this->container->get(TaskQueue::class)->queue([
            'task' => DeliverActivityTask::class,
            'params' => ['activity_id' => $id]
        ]);

        $db->update('activities', ['is_deleted' => 1], ['id' => $activityId]);
        $db->delete('notifications', ['activity_id' => $activityId]);
    }
}