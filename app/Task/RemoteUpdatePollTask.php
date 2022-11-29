<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\ActivityPub;
use Cherry\Helper\SignRequest;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class RemoteUpdatePollTask implements TaskInterface
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
        $objectRecord = $db->get('objects', '*', ['raw_object_id' => $object['id']]);
        if (
            $object['type'] !== ActivityPub::QUESTION
            || !isset($rawActivity['signature'])
            || empty($objectRecord)
        ) {
            return;
        }

        $profile = $db->get('profiles', '*', ['actor' => $actor]);
        $publicKey = $profile['public_key'];

        $helper = new SignRequest();
        $helper->withKey($publicKey);
        $verified = $helper->verifyLdSignature($rawActivity);
        if (!$verified) {
            throw new FailedTaskException('LD Signature verified failed');
        }
        $choices = [];
        $choicesKey = isset($object['oneOf']) ? 'oneOf' : 'anyOf';
        foreach ($object[$choicesKey] as $choice) {
            $choices[] = [
                'type' => $choice['type'],
                'name' => $choice['name'],
                'count' => $choice['replies']['totalItems'] ?? 0,
            ];
        }
        $poll = [
            'choices' => json_encode($choices, JSON_UNESCAPED_UNICODE),
            'voters_count' => $object['votersCount'],
        ];

        $db->update('polls', $poll, ['object_id' => $objectRecord['id']]);
    }
}
