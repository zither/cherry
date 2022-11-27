<?php

namespace Cherry\Task;

use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\Activity;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;

class RemoteDeleteTask implements TaskInterface
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
        if (empty($activity) || strtolower($activity['type']) !== 'delete') {
            throw new InvalidArgumentException('Invalid activity type');
        }
        $rawActivity = json_decode($activity['raw'], true);
        $activityType = Activity::createFromArray($rawActivity);

        if (is_array($rawActivity['object']) && $rawActivity['object']['type'] === 'Tombstone') {
            $task = new DeleteRemoteNoteTask($this->container);
            $task->command($args);
            return;
        }

        if (!is_string($activityType->object) || strpos($activityType->object, 'user') === false) {
            return;
        }

        $profile = $db->count('profiles', ['actor' => $activityType->object]);
        if (!$profile) {
            $db->delete('activities', ['id' => $activityId]);
        }
    }
}