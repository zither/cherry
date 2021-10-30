<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\Activity;
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

        $newActivityId = "{$profile['outbox']}/$snowflakeId";
        $published =  Time::UTCTimeISO8601();
        $object = [
            'id' => $newActivityId . '/object',
            'type' => 'Note',
            'attributedTo' => $profile['actor'],
            'name' => $choice['choice'],
            'to' => [$pollActivity->actor],
            'inReplyTo' => $pollActivity->id,
            'published' => $published,
            'content' => null,
            'cc' => [],
        ];

        $rawActivity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $newActivityId,
            'type' => 'Create',
            'actor' => $profile['actor'],
            'object' => $object,
            'to' => [$pollActivity->actor],
            'cc' => [],
        ];

        try {
            $db->pdo->beginTransaction();

            // 保存新 Object
            $db->insert('objects', [
                'type' => $object['type'],
                'profile_id' => 1,
                'raw_object_id' => $object['id'],
                'content' => $object['name'],
                'summary' => $object['summary'] ?? '',
                'url' => $object['url'] ?? '',
                'published' => Time::UTCToLocalTime($object['published']),
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
                'is_local' => 1,
                'is_public' => $object['is_public']
            ];
            $db->insert('activities', $activity);
            $activityId = $db->id();

            $db->insert('tasks', [
                'task' => 'DeliverActivityTask',
                'params' => json_encode(['activity_id' => $activityId], JSON_UNESCAPED_SLASHES),
                'priority' => 140,
            ]);

            $db->pdo->commit();
        } catch (\PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }
}