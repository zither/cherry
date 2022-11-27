<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\Activity;
use Cherry\ActivityPub\Context;
use Cherry\Helper\SignRequest;
use Cherry\Helper\Time;
use Godruoyi\Snowflake\Snowflake;
use Psr\Container\ContainerInterface;
use Medoo\Medoo;
use InvalidArgumentException;
use PDOException;

class LocalUndoTask implements TaskInterface
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
        if (empty($activity)) {
            throw new InvalidArgumentException('Invalid activity id: ' . $activityId);
        }
        $rawActivity = json_decode($activity['raw'], true);
        $activityType = Activity::createFromArray($rawActivity);
        $validTypes = [
            'Like',
            'Announce',
            'Accept',
            'Follow',
        ];
        if (!in_array($activityType->type, $validTypes)) {
            throw new InvalidArgumentException('Invalid activity type: ' . $activityType->type);
        }
        $rawObjectId = is_array($activityType->object) ? $activityType->object['id'] : $activityType->object;
        $targetObject = $db->get('objects', ['id', 'profile_id'], ['raw_object_id' => $rawObjectId]);
        if (empty($targetObject) && ($activityType->type === 'Like' || $activityType === 'Announce')) {
            throw new InvalidArgumentException('Object not found : ' . $rawObjectId);
        }
        $profile = $db->get('profiles', ['id', 'actor', 'outbox'], ['id' => CHERRY_ADMIN_PROFILE_ID]);

        $snowflake = $this->container->get(Snowflake::class);
        $snowflakeId = $snowflake->id();

        $rawOriginActivity = $rawActivity;
        unset($rawOriginActivity['@context']);

        $settings = $this->container->make('settings', ['keys' => ['domain']]);
        $undo = [
            'id' => "https://{$settings['domain']}/activities/$snowflakeId",
            'type' => 'Undo',
            'actor' => $profile['actor'],
            'object' => $rawOriginActivity,
        ];
        $undo = Context::set($undo, Context::OPTION_ACTIVITY_STREAMS);

        $helper = $this->container->get(SignRequest::class);
        $undo['signature'] = $helper->createLdSignature($undo);

        $newActivity = [
            'activity_id' => $undo['id'],
            'profile_id' => $profile['id'],
            'object_id' => $targetObject['id'] ?? 0,
            'type' => 'Undo',
            'raw' => json_encode($undo, JSON_UNESCAPED_SLASHES),
            'published' => Time::getLocalTime(),
            'is_local' => 1,
        ];
        try {
            $db->pdo->beginTransaction();
            $db->insert('activities', $newActivity);
            $newActivityId = $db->id();

            switch ($activityType->type) {
                case 'Like':
                    $column = 'likes';
                    break;
                case 'Announce':
                    $column = 'shares';
                    break;
            }
            if (!empty($column)) {
                $db->update('objects', ["{$column}[-]" => 1], ['id' => $targetObject['id']]);
                $types = ['likes' => 1, 'shares' => 2];
                $db->delete('interactions', [
                    'activity_id' => $activityId,
                    'object_id' => $targetObject['id'],
                    'profile_id' => $profile['id'],
                    'type' => $types[$column],
                ]);
                $db->update('activities', ['is_deleted' => 1], ['id' => $activityId]);
            }

            $this->container->get(TaskQueue::class)->queue([
                'task' => DeliverActivityTask::class,
                'params' => ['activity_id' => $newActivityId]
            ]);
            $db->pdo->commit();
        } catch (PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }
}