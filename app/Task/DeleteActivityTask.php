<?php

namespace Cherry\Task;

use adrianfalleiro\TaskInterface;
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
        $objectId = $db->get('activities', 'object_id', ['id' => $activityId]);
        $rawObjectId = $db->get('objects', 'raw_object_id', ['id' => $objectId]);
        $settings = $this->container->make('settings', ['keys' => ['domain']]);
        $helper = $this->container->get(SignRequest::class);
        $snowflake = $this->container->get(Snowflake::class);
        $newActivityId = $snowflake->id();
        $adminProfile = $db->get('profiles', ['id', 'outbox', 'actor'], ['id' =>  1]);
        $rawActivity = [
            'id' => "https://{$settings['domain']}/activities/{$newActivityId}",
            'type' => 'Delete',
            'actor' => $adminProfile['actor'],
            'object' => $rawObjectId,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        ];
        $rawActivity = Context::set($rawActivity, Context::OPTION_ACTIVITY_STREAMS | Context::OPTION_SECURITY_V1);
        $rawActivity['signature'] = $helper->createLdSignature($rawActivity);
        $activity = [
            'activity_id' => $rawActivity['id'],
            'type' => 'Delete',
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

        $db->delete('objects', ['id' => $objectId]);
        $db->update('activities', ['is_deleted' => 1], ['id' => $activityId]);
        $db->delete('notifications', ['activity_id' => $activityId]);
    }
}