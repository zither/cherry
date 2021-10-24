<?php

namespace Cherry\Task;

use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\Activity;
use Psr\Container\ContainerInterface;
use Medoo\Medoo;

class RemoteUndoTask implements TaskInterface
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
        if (empty($activity) || strtolower($activity['type']) !== 'undo') {
            throw new \InvalidArgumentException('Invalid activity type');
        }
        $rawActivity = json_decode($activity['raw'], true);
        $undoActivity = Activity::createFromArray($rawActivity['object']);
        switch ($undoActivity->lowerType()) {
            case 'follow':
                $actor = $db->get('profiles', 'actor', ['id' => 1]);
                if ($undoActivity->object !== $actor) {
                    return;
                }
                $followerProfile = $db->get('profiles', ['id'], ['actor' => $undoActivity->actor]);
                $db->delete('followers', ['profile_id' => $followerProfile['id']]);
                break;
            case 'like':
                $object = $db->get('objects', ['id'], ['raw_object_id' => $undoActivity->object]);
                if (empty($object)) {
                    return;
                }
                $profile = $db->get('profiles', ['id'], ['actor' => $undoActivity->actor]);
                if (empty($profile)) {
                    return;
                }
                $res = $db->delete('interactions', [
                    'object_id' => $object['id'],
                    'profile_id' => $profile['id'],
                    'type' => 1, //likes
                ]);
                $deleted = $res->rowCount() > 0;
                if ($deleted) {
                    $db->update('objects', ['likes[-]' => 1], ['id' => $object['id']]);
                }
                break;
            case 'announce':
                $db->update('activities', ['is_deleted' => 1], [
                    'OR' => [
                        'activity_id' => $undoActivity->id,
                        'id' => $activityId,
                    ]
                ]);
                break;
        }
    }
}