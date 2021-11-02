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
        if (empty($activity) || strtolower($activity['type']) !== 'create') {
            throw new InvalidArgumentException('Invalid activity type');
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

            if ($objectData['type'] === ActivityPub::OBJECT_TYPE_QUESTION) {
                $db->update('polls', ['activity_id' => $activityId], ['object_id' => $objectId]);
            }

            $db->pdo->commit();
        } catch (PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }
}