<?php

namespace Cherry\Task;

use Cherry\ActivityPub\Context;
use PDOException;
use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\Helper\SignRequest;
use Cherry\Helper\Time;
use Godruoyi\Snowflake\Snowflake;
use Psr\Container\ContainerInterface;
use Medoo\Medoo;
use InvalidArgumentException;

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
            throw new InvalidArgumentException('Invalid profile id: ' . $profileId);
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


        $settings = $this->container->make('settings', ['keys' => ['domain']]);
        $rawActivity = [
            'id' => "https://{$settings['domain']}/activities/$snowflakeId",
            'type' => 'Update',
            'actor' => $profile['actor'],
            'object' => $object,
            'to' => ["https://www.w3.org/ns/activitystreams#Public"],
        ];
        $rawActivity = Context::set($rawActivity, Context::OPTION_ACTIVITY_STREAMS | Context::OPTION_SECURITY_V1);
        $rawActivity['signature'] = $helper->createLdSignature($rawActivity);

        $activity = [
            'activity_id' => $rawActivity['id'],
            'profile_id' => $profile['id'],
            'object_id' => 0,
            'type' => 'Update',
            'raw' => json_encode($rawActivity, JSON_UNESCAPED_SLASHES),
            'published' => Time::getLocalTime(),
            'is_local' => 1,
        ];
        try {
            $db->pdo->beginTransaction();
            $db->insert('activities', $activity);
            $activityId = $db->id();
            $this->container->get(TaskQueue::class)->queue([
                'task' => DeliverActivityTask::class,
                'params' => ['activity_id' => $activityId]
            ]);
            $db->pdo->commit();
        } catch (PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }
}