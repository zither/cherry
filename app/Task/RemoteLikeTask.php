<?php

namespace Cherry\Task;

use Cherry\ActivityPub\ActivityPub;
use InvalidArgumentException;
use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Psr\Container\ContainerInterface;
use Medoo\Medoo;
use PDOException;

class RemoteLikeTask implements TaskInterface
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
        if (empty($activity) || $activity['type'] !== ActivityPub::LIKE) {
            throw new InvalidArgumentException('Invalid activity type');
        }
        $rawActivity = json_decode($activity['raw'], true);

        $actor = $rawActivity['actor'];
        if (is_string($rawActivity['object'])) {
            $rawObjectId = $rawActivity['object'];
        } else if (is_array($rawActivity['object'])) {
            $rawObjectId = $rawActivity['object']['id'];
        } else {
            throw new InvalidArgumentException('Invalid object');
        }
        $object = $db->get('objects', ['id', 'profile_id', 'is_local'], ['raw_object_id' => $rawObjectId]);
        if (empty($object)) {
            // 嘟文不存在，直接结束
            return;
            //throw new RetryException("Object not found: {$rawObjectId}");
        }
        $profile = $db->get('profiles', ['id'], ['actor' => $actor]);
        if (empty($profile)) {
            $subTask = new FetchProfileTask($this->container);
            $profile = $subTask->command(['actor' => $actor]);
        }
        $types = [
            ActivityPub::LIKE => 1,
            ActivityPub::ANNOUNCE => 2,
        ];
        try {
            $column = $activity['type'] == ActivityPub::LIKE ? 'likes' : 'shares';
            $db->pdo->beginTransaction();
            $db->update('objects', ["{$column}[+]" => 1], ['raw_object_id' => $rawObjectId]);
            // local notes
            if ($object['is_local']) {
                $db->insert('interactions', [
                    'activity_id' => $activityId,
                    'object_id' => $object['id'],
                    'profile_id' => $profile['id'],
                    'type' => $types[$rawActivity['type']],
                    'published' => $activity['published'],
                ]);
                if ($object['profile_id'] !== $profile['id']) {
                    $db->insert('notifications', [
                        'actor' => $actor,
                        'profile_id' => $profile['id'],
                        'activity_id' => $activityId,
                        'type' => ActivityPub::LIKE,
                        'status' => 1,
                    ]);
                }
            }
            $db->update('activities', [
                'object_id' => $object['id'],
                'profile_id' => $profile['id']
            ], ['id' => $activityId]);
            $db->pdo->commit();
        } catch (PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }
}