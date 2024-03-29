<?php

namespace Cherry\Task;

use adrianfalleiro\TaskInterface;
use Psr\Container\ContainerInterface;
use Cherry\ActivityPub\ActivityPub;
use Medoo\Medoo;
use InvalidArgumentException;

class AnnounceRequestTask implements TaskInterface
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
        if (empty($activity) || $activity['type'] !== ActivityPub::ANNOUNCE) {
            throw new InvalidArgumentException('Invalid activity type');
        }
        $rawActivity = json_decode($activity['raw'], true);

        $activityProfileId = $db->get('profiles', 'id', ['actor' => $rawActivity['actor']]);
        if (empty($activityProfileId)) {
            $fetchProfileTask = new FetchProfileTask($this->container);
            $profile = $fetchProfileTask->command(['actor' => $rawActivity['actor']]);
            $activityProfileId = $profile['id'];
        }

        if (is_string($rawActivity['object'])) {
            $rawObjectId = $rawActivity['object'];
        } else if (is_array($rawActivity['object']) && !empty($rawActivity['object']['id'])) {
            $rawObjectId = $rawActivity['object']['id'];
        } else {
            throw new InvalidArgumentException('Invalid Object type');
        }
        $object = $db->get('objects', ['id', 'is_local'], ['raw_object_id' => $rawObjectId]);
        if (empty($object)) {
            $fetchObjectTask = new FetchObjectTask($this->container);
            $object = $fetchObjectTask->command(['id' => $rawActivity['object'], 'is_boosted' => 1]);
        }

        $db->update('activities', [
            'object_id' => $object['id'],
            'profile_id' => $activityProfileId
        ], ['id' => $activityId]);

        if ($object['is_local']) {
            $db->insert('interactions', [
                'activity_id' => $activityId,
                'object_id' => $object['id'],
                'profile_id' => $activityProfileId,
                'type' => 2,
                'published' => $activity['published'],
            ]);
            $db->update('objects', ['shares[+]' => 1], ['id' => $object['id']]);
        }
    }
}