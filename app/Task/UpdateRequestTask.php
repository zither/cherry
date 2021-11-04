<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\ActivityPub;
use Cherry\Helper\SignRequest;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class UpdateRequestTask implements TaskInterface
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
        $rawActivity = json_decode($activity['raw'], true);
        $actor = $rawActivity['actor'];
        $object = $rawActivity['object'];

        if ($object['type'] === ActivityPub::OBJECT_TYPE_QUESTION) {
            $this->container->get(TaskQueue::class)->queue([
                'task' => RemoteUpdatePollTask::class,
                'params' => $args
            ]);
            return;
        }

        if (!is_array($object) || $object['type'] !== 'Person' || !isset($rawActivity['signature'])) {
            return;
        }
        $person = $object;
        $profile = $db->get('profiles', '*', ['actor' => $actor]);
        if (isset($person['publicKey']['publicKeyPem'])) {
            $publicKey = $person['publicKey']['publicKeyPem'];
        } else {
            $publicKey = $profile['public_key'];
        }

        $helper = new SignRequest();
        $helper->withKey($publicKey);
        $verified = $helper->verifyLdSignature($rawActivity);
        if (!$verified) {
            throw new FailedTaskException('LD Signature verified failed');
        }
        $newProfile = [
            'actor' => $actor,
            'name' => $person['name'] ?? '',
            'preferred_name' => $person['preferredUsername'],
            'url' => $person['url'] ?? '',
            'inbox' => $person['inbox'] ?? '',
            'outbox' => $person['outbox'] ?? '',
            'following' => $person['following'] ?? '',
            'followers' => $person['followers'] ?? '',
            'public_key' => $person['publicKey']['publicKeyPem'] ?? '',
            'likes' => $person['likes'] ?? '',
            'avatar' => $person['icon']['url'] ?? '',
            'summary' => $person['summary'] ?? '',
            'shared_inbox' => $person['endpoints']['sharedInbox'] ?? '',
            'featured' => $person['featured'] ?? '',
        ];
        if (!empty($profile)) {
            $db->update('profiles', $newProfile, ['actor' => $actor]);
        } else {
            $db->insert('profiles', $newProfile);
        }
    }
}