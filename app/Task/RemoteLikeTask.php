<?php

namespace Cherry\Task;

use InvalidArgumentException;
use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Psr\Container\ContainerInterface;
use Medoo\Medoo;

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
        if (empty($activity) || strtolower($activity['type']) !== 'like') {
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
        $object = $db->get('objects', ['id', 'is_local'], ['raw_object_id' => $rawObjectId]);
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
            'Like' => 1,
            'Announce' => 2,
        ];
        try {
            $column = $activity['type'] == 'Like' ? 'likes' : 'shares';
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
            }
            $db->update('activities', [
                'object_id' => $object['id'],
                'profile_id' => $profile['id']
            ], ['id' => $activityId]);
            $db->pdo->commit();
        } catch (\PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }
}