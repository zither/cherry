<?php

namespace Cherry\Task;

use PDOException;
use InvalidArgumentException;
use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\ActivityPub;
use Psr\Container\ContainerInterface;
use Medoo\Medoo;

class CreateRequestTask implements TaskInterface
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
        if (empty($activity) || $activity['type'] !== ActivityPub::CREATE) {
            throw new InvalidArgumentException('Invalid activity type: ' . $activity['type'] ?? '');
        }

        // Check if this activity is duplicate
        $exits = $db->count('activities', [
            'activity_id' => $activity['activity_id'],
            'object_id[>]' => 0,
        ]);
        if ($exits) {
            $db->update('activities', ['is_deleted' => 1], ['id' => $activityId]);
            return;
        }

        $rawActivity = json_decode($activity['raw'], true);
        $processObjectTask = new FetchObjectTask($this->container);
        $objectData = $processObjectTask->process($rawActivity['object']);
        $objectId = $objectData['id'];

        try {
            $db->pdo->beginTransaction();
            $updatedData = [
                'object_id' => $objectId,
                'profile_id' => $objectData['profile_id'],
            ];
            if ($objectData['unlisted']) {
                $updatedData['unlisted'] = $objectData['unlisted'];
            }
            $db->update('activities', $updatedData, ['id' => $activityId]);

            if ($objectData['type'] === ActivityPub::QUESTION) {
                $db->update('polls', ['activity_id' => $activityId], ['object_id' => $objectId]);
            }

            if ($objectData['parent_id']) {
                $localParentId = $db->get('objects', 'profile_id', [
                    'id' => $objectData['parent_id'],
                    'profile_id' => CHERRY_ADMIN_PROFILE_ID,
                ]);
                if ($localParentId) {
                    $db->insert('notifications', [
                        'actor' => $rawActivity['actor'],
                        'profile_id' => $objectData['profile_id'],
                        'activity_id' => $activityId,
                        'type' => 'Reply',
                        'status' => 1,
                    ]);
                }
            }

            if (!empty($rawActivity['object']['tag'])) {
                $mentioned = false;
                $ownerActor = $db->get('profiles', 'actor', ['id' => 1]);
                foreach ($rawActivity['object']['tag'] as $v) {
                    if (!isset($v['type']) || $v['type'] !== 'Mention') {
                        continue;
                    }
                    if ($v['href'] === $ownerActor) {
                        $mentioned = true;
                        break;
                    }
                }
                if ($mentioned) {
                    $db->insert('notifications', [
                        'actor' => $rawActivity['actor'],
                        'profile_id' => $objectData['profile_id'],
                        'activity_id' => $activityId,
                        'type' => 'Mention',
                        'status' => 1,
                    ]);
                }
            }

            // Deliver reply activity to followers
            if ($activity['is_public'] && $objectData['reply_to_local_object']) {
                $taskQueue = new TaskQueue($this->container);
                $taskQueue->queue([
                    'task' => DeliverActivityTask::class,
                    'params' => ['activity_id' => $activityId]
                ]);
            }

            $db->pdo->commit();
        } catch (PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }
}