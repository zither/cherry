<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\RetryException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\Activity;
use Cherry\Helper\SignRequest;
use Cherry\Helper\Time;
use Godruoyi\Snowflake\Snowflake;
use Psr\Container\ContainerInterface;
use Medoo\Medoo;

class LocalUndoTask implements TaskInterface
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
        if (empty($activity)) {
            throw new \InvalidArgumentException('Invalid activity id: ' . $activityId);
        }
        $rawActivity = json_decode($activity['raw'], true);
        $activityType = Activity::createFromArray($rawActivity);
        $validTypes = [
            'Like',
            'Announce',
            'Accept',
            'Follow',
        ];
        if (!in_array($activityType->type, $validTypes)) {
            throw new \InvalidArgumentException('Invalid activity type: ' . $activityType->type);
        }
        $targetObject = $db->get('objects', ['id', 'profile_id'], ['raw_object_id' => $activityType->object]);
        if (empty($targetObject) && ($activityType->type === 'Like' || $activityType === 'Announce')) {
            throw new \InvalidArgumentException('Object not found : ' . $activityType->object);
        }
        $profile = $db->get('profiles', ['id', 'actor', 'outbox'], ['id' => 1]);

        $snowflake = $this->container->get(Snowflake::class);
        $snowflakeId = $snowflake->id();

        $origin = $db->get('activities', '*', ['id' => $activityId]);
        $rawOriginActivity = json_decode($origin['raw'], true);
        unset($rawOriginActivity['@context']);

        $undo = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => "{$profile['outbox']}/$snowflakeId",
            'type' => 'Undo',
            'actor' => $profile['actor'],
            'object' => $rawOriginActivity,
        ];

        $helper = $this->container->get(SignRequest::class);
        $undo['signature'] = $helper->createLdSignature($undo);

        $newActivity = [
            'activity_id' => $undo['id'],
            'profile_id' => $profile['id'],
            'object_id' => $targetObject['id'] ?? 0,
            'type' => 'Undo',
            'raw' => json_encode($undo, JSON_UNESCAPED_SLASHES),
            'published' => Time::getLocalTime(),
            'is_local' => 1,
        ];
        try {
            $db->pdo->beginTransaction();
            $db->insert('activities', $newActivity);
            $newActivityId = $db->id();

            switch ($activityType->type) {
                case 'Like':
                    $column = 'likes';
                    break;
                case 'Announce':
                    $column = 'shares';
                    break;
            }
            if (!empty($column)) {
                $db->update('objects', ["{$column}[-]" => 1], ['id' => $targetObject['id']]);
                $types = ['likes' => 1, 'shares' => 2];
                $db->delete('interactions', [
                    'activity_id' => $activityId,
                    'object_id' => $targetObject['id'],
                    'profile_id' => $profile['id'],
                    'type' => $types[$column],
                ]);
                $db->update('activities', ['is_deleted' => 1], ['id' => $activityId]);
            }

            $db->insert('tasks', [
                'task' => 'DeliverActivityTask',
                'params' => json_encode(['activity_id' => $newActivityId], JSON_UNESCAPED_SLASHES),
                'priority' => 140,
            ]);
            $db->pdo->commit();
        } catch (\PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }
}