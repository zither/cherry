<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\Helper\SignRequest;
use Cherry\Helper\Time;
use Godruoyi\Snowflake\Snowflake;
use Psr\Container\ContainerInterface;
use Medoo\Medoo;

class LocalUpdateProfileTask implements TaskInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function command(array $args)
    {
        $profileId = $args['id'];
        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', '*', ['id' => $profileId]);
        if (empty($profile)) {
            throw new \InvalidArgumentException('Invalid profile id: ' . $profileId);
        }

        $helper = $this->container->get(SignRequest::class);
        $snowflake = $this->container->get(Snowflake::class);
        $snowflakeId = $snowflake->id();
        $object = [
            'type' => 'Person',
            'id' => $profile['actor'],
            'url' => $profile['url'],
            'preferredUsername' => $profile['preferred_name'],
            'name' => $profile['name'],
            'summary' => $profile['summary'],
            "manuallyApprovesFollowers" => true,
            'inbox' => $profile['inbox'],
            'outbox' => $profile['outbox'],
            'followers' => $profile['followers'],
            'following' => $profile['following'],
            "icon" => [
                "mediaType" => "image/png",
                "type" => "Image",
                "url" => $profile['avatar']
            ],
            'publicKey' => [
                'id' => "{$profile['actor']}#main-key",
                'owner' => $profile['actor'],
                'publicKeyPem' => $profile['public_key'],
                'type' => 'Key'
            ]
        ];

        $rawActivity = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                "https://w3id.org/security/v1",
            ],
            'id' => "{$profile['outbox']}/$snowflakeId",
            'type' => 'Update',
            'actor' => $profile['actor'],
            'object' => $object,
            'to' => ["https://www.w3.org/ns/activitystreams#Public"],
        ];
        $rawActivity['signature'] = $helper->createLdSignature($rawActivity);

        $activity = [
            'activity_id' => $rawActivity['id'],
            'profile_id' => $profile['id'],
            'object_id' => 0,
            'type' => 'Update',
            'raw' => json_encode($rawActivity, JSON_UNESCAPED_SLASHES),
            'published' => Time::utc(),
            'is_local' => 1,
        ];
        try {
            $db->pdo->beginTransaction();
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