<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\Activity;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class DeliverActivityTask implements TaskInterface
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
        if (empty($activity)) {
            throw new FailedTaskException("Invalid activity id: $activityId");
        }

        $rawActivity = json_decode($activity['raw'], true);
        $activityType = Activity::createFromArray($rawActivity);

        $profile = $db->get('profiles', ['id', 'inbox', 'followers'], ['id' => 1]);
        $inboxes = $this->getInboxesFromActivity($activityType, $profile);

        // if inboxes was empty, try to find inboxes from origin activity.
        if (empty($inboxes)) {
            if ($activityType->type === 'Undo') {
                if (is_array($activityType->object)) {
                    $originActivityType = Activity::createFromArray($activityType->object);
                } else {
                    $originActivity = $db->get('activities', ['id', 'raw'], ['activity_id' => $activityType->object]);
                    $originActivityType = Activity::createFromArray(json_decode($originActivity['raw'], true));
                }
            } else if ($activityType->type === 'Delete') {
                //@TODO Remove
                if (is_string($activityType->object)) {
                    $deletedObjectId = $db->get('objects', 'id', ['raw_object_id' => $activityType->object]);
                    $originActivityTypeId = str_replace('/object', '', $activityType->object);
                    $originActivity = $db->get('activities', ['id', 'raw'], [
                        'OR' => [
                            'object_id' => $deletedObjectId,
                            'activity_id' => $originActivityTypeId
                        ],
                        'type' => 'Create'
                    ]);
                    $originActivityType = Activity::createFromArray(json_decode($originActivity['raw'], true));
                }
            }
            if (!empty($originActivityType)) {
                $inboxes = $this->getInboxesFromActivity($originActivityType, $profile);
            }
        }

        if (empty($inboxes)) {
            throw new FailedTaskException('Audience Required: ' . $activityId);
        }

        $tasks = [];
        foreach ($inboxes as $v) {
            if ($v === $profile['inbox']) {
                continue;
            }
            $params = json_encode([
                'activity_id' => $activityId,
                'inbox' => $v,
            ], JSON_UNESCAPED_SLASHES);
            $tasks[] = [
                'task' => 'PushActivityTask',
                'params' => $params,
                'priority' => 140,
            ];
        }
        $db->insert('tasks', $tasks);
    }

    protected function getInboxesFromActivity(Activity $activityType, array $profile): array
    {
        $db = $this->container->get(Medoo::class);
        $actors = [];
        $toFollowers = false;
        foreach ($activityType->audiences() as $v) {
            if ($v === Activity::PUBLIC_COLLECTION || $v === $profile['followers']) {
                $toFollowers = true;
            } else  {
                $actors[] = $v;
            }
        }

        if ($activityType->type === 'Follow') {
            $actors = array_merge($actors, [$activityType->object]);
        }

        if (is_array($activityType->object)) {
            $this->getActorsRecursivelyFromObject($activityType->object, $actors);
        }

        $targets = [];
        if (!empty($actors)) {
            $profiles = $db->select('profiles', ['inbox', 'shared_inbox'], ['actor' => $actors]);
            $targets = array_merge($targets, $profiles);
        }
        if ($toFollowers) {
            $followers = $db->select('followers', [
                '[>]profiles' => ['profile_id' => 'id']
            ], [
                'followers.id',
                'followers.profile_id',
                'profiles.inbox',
                'profiles.shared_inbox',
            ]);
            $targets = array_merge($targets, $followers);
        }

        $inboxes = [];
        foreach ($targets as $v) {
            if (($activityType->isPublic() || $toFollowers) && !empty($v['shared_inbox'])) {
                if (!in_array($v['shared_inbox'], $inboxes)) {
                    $inboxes[] = $v['shared_inbox'];
                }
            } else {
                $inboxes[] = $v['inbox'];
            }
        }

        return $inboxes;
    }

    protected function getActorsRecursivelyFromObject(array $object, array &$actors)
    {
        if (isset($object['actor'])) {
            if (!in_array($object['actor'], $actors)) {
                $actors[] = $object['actor'];
            }
        }
        if (isset($object['object']) && is_array($object['object'])) {
            $this->getActorsRecursivelyFromObject($object['object'], $actors);
        }
    }
}