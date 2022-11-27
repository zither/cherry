<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\Activity;
use Cherry\ActivityPub\Context;
use Cherry\Helper\Time;
use Godruoyi\Snowflake\Snowflake;
use Psr\Container\ContainerInterface;
use Medoo\Medoo;

class LocalVoteTask implements TaskInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function command(array $args)
    {
        $db = $this->container->get(Medoo::class);
        $choiceId = $args['choice_id'];
        $choice = $db->get('poll_choices', '*', ['id' => $choiceId]);
        $poll = $db->get('polls', '*', ['id' => $choice['poll_id']]);
        $pollActivityRecord = $db->get('activities', '*', ['id' => $poll['activity_id']]);
        $pollActivity = Activity::createFromArray(json_decode($pollActivityRecord['raw'], true));

        $snowflake = $this->container->get(Snowflake::class);
        $snowflakeId = $snowflake->id();
        $profile = $db->get('profiles', ['id', 'actor', 'outbox', 'followers'], ['id' => 1]);
        $settings = $this->container->make('settings', ['keys' => ['domain']]);

        $newActivityId = "https://{$settings['domain']}/activities/$snowflakeId";
        $published =  Time::UTCTimeISO8601();
        $objectPublicId = $snowflake->id();
        $object = [
            'id' => "https://{$settings['domain']}/objects/$objectPublicId",
            'type' => 'Note',
            'attributedTo' => $profile['actor'],
            'name' => $choice['choice'],
            'to' => [$pollActivity->actor],
            'inReplyTo' => $pollActivity->object['id'],
            'published' => $published,
            'content' => null,
            'cc' => [],
        ];

        $rawActivity = [
            'id' => $newActivityId,
            'type' => 'Create',
            'actor' => $profile['actor'],
            'object' => $object,
            'to' => [$pollActivity->actor],
            'cc' => [],
        ];
        $rawActivity = Context::set($rawActivity, Context::OPTION_ACTIVITY_STREAMS);

        try {
            $db->pdo->beginTransaction();

            // 保存新 Object
            $db->insert('objects', [
                'type' => $object['type'],
                'profile_id' => CHERRY_ADMIN_PROFILE_ID,
                'raw_object_id' => $object['id'],
                'content' => $object['name'],
                'summary' => $object['summary'] ?? '',
                'url' => $object['url'] ?? '',
                'published' => Time::UTCToLocalTime($object['published']),
                'unlisted' => 1,
                'is_local' => 1,
                'is_public' => 0,
                'origin_id' => $poll['object_id'],
                'parent_id' => $poll['object_id'],
            ]);
            $objectId = $db->id();

            $activity = [
                'activity_id' => $rawActivity['id'],
                'profile_id' => $profile['id'],
                'object_id' => $objectId,
                'type' => $rawActivity['type'],
                'raw' => json_encode($rawActivity, JSON_UNESCAPED_SLASHES),
                'published' => Time::getLocalTime(),
                'unlisted' => 1,
                'is_local' => 1,
                'is_public' => $pollActivity->isPublic() ? 1 : 0,
            ];
            $db->insert('activities', $activity);
            $activityId = $db->id();

            $this->container->get(TaskQueue::class)->queue([
                'task' => DeliverActivityTask::class,
                'params' => ['activity_id' => $activityId]
            ]);
            $db->update('poll_choices', [
                'activity_id' => $activityId,
                'object_id' => $objectId
            ], ['id' => $choiceId]);

            $db->pdo->commit();
        } catch (\PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }
}