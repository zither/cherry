<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\ActivityPub;
use Cherry\ActivityPub\Context;
use Cherry\Helper\Time;
use Godruoyi\Snowflake\Snowflake;
use Psr\Container\ContainerInterface;
use Medoo\Medoo;
use InvalidArgumentException;
use PDOException;

class LocalInteractiveTask implements TaskInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function command(array $args)
    {
        $db = $this->container->get(Medoo::class);
        $objectId = $args['object_id'];
        $object = $db->get('objects', ['id', 'raw_object_id', 'profile_id', 'is_public'], ['id' => $objectId]);
        if (empty($object)) {
            throw new InvalidArgumentException('Invalid object id: ' . $objectId);
        }
        $interaction = $args['type'];
        if (!in_array($interaction, [ActivityPub::LIKE, ActivityPub::ANNOUNCE])) {
            throw new InvalidArgumentException('Invalid interaction type: ' . $interaction);
        }

        $snowflake = $this->container->get(Snowflake::class);
        $snowflakeId = $snowflake->id();
        $profile = $db->get('profiles', ['id', 'actor', 'outbox', 'followers'], ['id' => CHERRY_ADMIN_PROFILE_ID]);
        $targetProfile = $db->get('profiles', ['id', 'actor', 'inbox'], ['id' => $object['profile_id']]);
        $settings = $this->container->make('settings', ['keys' => ['domain']]);
        $rawActivity = [
            'id' => "https://{$settings['domain']}/activities/$snowflakeId",
            'type' => $interaction,
            'actor' => $profile['actor'],
            'object' => $object['raw_object_id'],
            'to' => [$targetProfile['actor']],
        ];
        $rawActivity = Context::set($rawActivity, Context::OPTION_ACTIVITY_STREAMS);
        if ($interaction !== ActivityPub::LIKE) {
            $rawActivity = array_merge($rawActivity, [
                'cc' => [
                    "https://www.w3.org/ns/activitystreams#Public",
                    $profile['followers'],
                ],
            ]);
        }

        $activity = [
            'activity_id' => $rawActivity['id'],
            'profile_id' => $profile['id'],
            'object_id' => $objectId,
            'type' => $interaction,
            'raw' => json_encode($rawActivity, JSON_UNESCAPED_SLASHES),
            'published' => Time::getLocalTime(),
            'is_local' => 1,
            'is_public' => $object['is_public']
        ];
        try {
            $db->pdo->beginTransaction();
            $db->insert('activities', $activity);
            $activityId = $db->id();

            switch ($interaction) {
                case ActivityPub::LIKE:
                    $column = 'likes';
                    break;
                case ActivityPub::ANNOUNCE:
                    $column = 'shares';
                    break;
            }
            if (!empty($column)) {
                $db->update('objects', ["{$column}[+]" => 1], ['id' => $objectId]);
            }
            $this->container->get(TaskQueue::class)->queue([
                'task' => DeliverActivityTask::class,
                'params' => ['activity_id' => $activityId]
            ]);

            $types = ['likes' => 1, 'shares' => 2];
            $db->insert('interactions', [
                'activity_id' => $activityId,
                'object_id' => $objectId,
                'profile_id' => $profile['id'],
                'type' => $types[$column],
                'published' => $activity['published'],
            ]);

            $db->pdo->commit();
        } catch (PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }
}