<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\Helper\Time;
use Godruoyi\Snowflake\Snowflake;
use Psr\Container\ContainerInterface;
use Medoo\Medoo;

class LocalInteractiveTask implements TaskInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function command(array $args)
    {
        $db = $this->container->get(Medoo::class);
        $objectId = $args['object_id'];
        $object = $db->get('objects', ['id', 'raw_object_id', 'profile_id', 'is_public'], ['id' => $objectId]);
        if (empty($object)) {
            throw new \InvalidArgumentException('Invalid object id: ' . $objectId);
        }
        $interaction = $args['type'];
        if (!in_array($interaction, ['Like', 'Announce'])) {
            throw new \InvalidArgumentException('Invalid interaction type: ' . $interaction);
        }

        $snowflake = $this->container->get(Snowflake::class);
        $snowflakeId = $snowflake->id();
        $profile = $db->get('profiles', ['id', 'actor', 'outbox', 'followers'], ['id' => 1]);
        $targetProfile = $db->get('profiles', ['id', 'actor', 'inbox'], ['id' => $object['profile_id']]);

        $rawActivity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => "{$profile['outbox']}/$snowflakeId",
            'type' => $interaction,
            'actor' => $profile['actor'],
            'object' => $object['raw_object_id'],
            'to' => [$targetProfile['actor']],
        ];
        if ($interaction !== 'Like') {
            $rawActivity = array_merge($rawActivity, [
                'cc' => [
                    "https://www.w3.org/ns/activitystreams#Public",
                    $profile['followers'],
                ],
            ]);
        }

        $activity = [
            'activity_id' => $rawActivity['id'],
            'profile_id' => $profile['id'],
            'object_id' => $objectId,
            'type' => $interaction,
            'raw' => json_encode($rawActivity, JSON_UNESCAPED_SLASHES),
            'published' => Time::utc(),
            'is_local' => 1,
            'is_public' => $object['is_public']
        ];
        try {
            $db->pdo->beginTransaction();
            $db->insert('activities', $activity);
            $activityId = $db->id();

            switch ($interaction) {
                case 'Like':
                    $column = 'likes';
                    break;
                case 'Announce':
                    $column = 'shares';
                    break;
            }
            if (!empty($column)) {
                $db->update('objects', ["{$column}[+]" => 1], ['id' => $objectId]);
            }
            $db->insert('tasks', [
                'task' => 'DeliverActivityTask',
                'params' => json_encode(['activity_id' => $activityId], JSON_UNESCAPED_SLASHES),
                'priority' => 140,
            ]);

            $types = ['likes' => 1, 'shares' => 2];
            $db->insert('interactions', [
                'activity_id' => $activityId,
                'object_id' => $objectId,
                'profile_id' => $profile['id'],
                'type' => $types[$column],
                'published' => Time::utc($activity['published']),
            ]);

            $db->pdo->commit();
        } catch (\PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }
}