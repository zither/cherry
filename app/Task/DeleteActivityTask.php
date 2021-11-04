<?php

namespace Cherry\Task;

use adrianfalleiro\TaskInterface;
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
        $activityIdentity = $db->get('activities', 'activity_id', ['id' => $activityId]);
        $helper = $this->container->get(SignRequest::class);
        $snowflake = $this->container->get(Snowflake::class);
        $newActivityId = $snowflake->id();
        $adminProfile = $db->get('profiles', ['id', 'outbox', 'actor'], ['id' =>  1]);
        $rawActivity = [
            "@context" => [
                "https://www.w3.org/ns/activitystreams",
                "https://w3id.org/security/v1",
            ],
            'id' => "{$adminProfile['outbox']}/{$newActivityId}",
            'type' => 'Delete',
            'actor' => $adminProfile['actor'],
            'object' => "$activityIdentity/object",
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        ];
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
        $this->container->get(TaskFactory::class)->queue(DeliverActivityTask::class, ['activity_id' => $id]);
        $db->update('activities', ['is_deleted' => 1], ['id' => $activityId]);
        $db->delete('notifications', ['activity_id' => $rawActivity['id']]);
    }
}