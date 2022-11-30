<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\Activity;
use Cherry\ActivityPub\ActivityPub;
use Cherry\ActivityPub\ObjectType;
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

        $profile = $db->get('profiles', ['id', 'actor', 'inbox', 'shared_inbox', 'followers'], [
            'id' => CHERRY_ADMIN_PROFILE_ID
        ]);
        $inboxes = $this->getInboxesFromActivity($activityType, $profile);

        // if inboxes was empty, try to find inboxes from origin activity.
        if (empty($inboxes)) {
            if ($activityType->type === ActivityPub::UNDO) {
                if (is_array($activityType->object)) {
                    $originActivityType = Activity::createFromArray($activityType->object);
                } else {
                    $originActivity = $db->get('activities', ['id', 'raw'], ['activity_id' => $activityType->object]);
                    $originActivityType = Activity::createFromArray(json_decode($originActivity['raw'], true));
                }
            } else if ($activityType->type === ActivityPub::DELETE) {
                //@TODO Remove
                if (is_string($activityType->object)) {
                    $deletedObjectId = $db->get('objects', 'id', ['raw_object_id' => $activityType->object]);
                    $originActivityTypeId = str_replace('/object', '', $activityType->object);
                    $originActivity = $db->get('activities', ['id', 'raw'], [
                        'OR' => [
                            'object_id' => $deletedObjectId,
                            'activity_id' => $originActivityTypeId
                        ],
                        'type' => ActivityPub::CREATE
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
            if ($v === $profile['inbox'] || $v === $profile['shared_inbox']) {
                continue;
            }
            $params = [
                'activity_id' => $activityId,
                'inbox' => $v,
            ];
            $tasks[] = [
                'task' => PushActivityTask::class,
                'params' => $params,
                'priority' => 140,
            ];
        }

        if (empty($tasks)) {
            throw new FailedTaskException('No Task queued: ' . $activityId);
        }
        $taskQueue = new TaskQueue($this->container);
        $taskQueue->queueArray($tasks);
    }

    protected function getInboxesFromActivity(Activity $activityType, array $profile): array
    {
        $db = $this->container->get(Medoo::class);
        $actors = [];
        $this->getActorsRecursivelyFromObject($activityType, $actors);
        $actors = array_unique($actors);
        $toFollowers = false;
        foreach ($actors as $k => $v) {
            if ($v === $profile['actor'] || $v === $activityType->actor) {
                unset($actors[$k]);
                continue;
            }
            if ($v === Activity::PUBLIC_COLLECTION || $v === $profile['followers']) {
                $toFollowers = true;
            }
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

    protected function getActorsRecursivelyFromObject(ObjectType $object, array &$actors)
    {
        foreach ($object->audiences() as $v) {
            $actors[] = $v;
        }

        if (isset($object->actor)) {
            $actors[] = $object->actor;
        }

        if (isset($object->object)) {
            if (is_string($object->object)) {
                if ($object->type === ActivityPub::FOLLOW) {
                    $actors[] = $object->object;
                }
            } else if (is_array($object->object)) {
                $subObject = ObjectType::createFromArray($object->object);
                $this->getActorsRecursivelyFromObject($subObject, $actors);
            }
        }
    }
}