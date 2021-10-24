<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Psr\Container\ContainerInterface;
use Medoo\Medoo;
use PDOException;

class DeleteRemoteNoteTask implements TaskInterface
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
        $activity = $db->get('activities', '*', ['id' => $activityId]);
        $rawActivity = json_decode($activity['raw'], true);
        if (
            $rawActivity['type'] !== 'Delete'
            || empty($rawActivity['object']['type'])
            || $rawActivity['object']['type'] !== 'Tombstone'
        ) {
            return;
        }
        $rawObjectId = $rawActivity['object']['id'];
        $object = $db->get('objects', '*', ['raw_object_id' => $rawObjectId]);
        try {
            $db->pdo->beginTransaction();
            $db->delete('objects', ['id' => $object['id']]);
            $db->update('activities', ['is_deleted' => 1], [
                'OR' => [
                    'id' => $activityId,
                    'object_id' => $object['id'],
                ]
            ]);
            $db->delete('interactions', ['object_id' => $object['id']]);
            if (!empty($object['parent_id'])) {
                $db->update('objects', ['replies[-]' => 1], ['id' => $object['parent_id']]);
            }
            $db->pdo->commit();
        } catch (PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }
}