<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\ObjectType;
use Cherry\Helper\Time;
use Psr\Container\ContainerInterface;
use Medoo\Medoo;

class CreateRequestTask implements TaskInterface
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
        if (empty($activity) || strtolower($activity['type']) !== 'create') {
            throw new \InvalidArgumentException('Invalid activity type');
        }
        $rawActivity = json_decode($activity['raw'], true);
        $object = ObjectType::createFromArray($rawActivity['object']);
        $profile = $db->get('profiles', ['id'], ['actor' => $object->attributedTo]);
        if (empty($profile)) {
            $subTask = new FetchProfileTask($this->container);
            $profile = $subTask->command(['actor' => $object->attributedTo]);
        }
        $originId = 0;
        $parentId = 0;
        if ($object->isReply()) {
            $parent = $db->get('objects', ['id', 'origin_id', 'parent_id'], ['raw_object_id' => $object->inReplyTo]);
            if (!empty($parent)) {
                $originId = $parent['origin_id'] ?: $parent['id'];
                $parentId = $parent['id'];
            }
        }

        try {
            $db->pdo->beginTransaction();
            $db->insert('objects', [
                'profile_id' => $profile['id'],
                'origin_id' => $originId,
                'parent_id' => $parentId,
                'raw_object_id' => $object->id,
                'type' => $object->type,
                'content' => $object->getStringAttribute('content'),
                'summary' => $object->getStringAttribute('summary'),
                'url' => $object->getStringAttribute('url') ?: $object->id,
                'published' => Time::utc($object->published),
                'is_local' => 0,
                'is_public' => $object->isPublic(),
                'is_boosted' => 0,
                'is_sensitive' => isset($object->sensitive) && $object->sensitive,
            ]);
            $objectId = $db->id();

            if (!empty($object->tag)) {
                $tags = [];
                foreach ($object->tag as $v) {
                    $tags[] = [
                        'term' => trim($v['name'], '#'),
                        'object_id' => $objectId,
                        'profile_id' => $profile['id'],
                    ];
                }
                $db->insert('tags', $tags);
            }

            if (!empty($object->attachment)) {
                $attachments = [];
                foreach ($object->attachment as $v) {
                    $attachments[] = [
                        'profile_id' => $profile['id'],
                        'object_id' => $objectId,
                        'type' => $v['type'] ?? '',
                        'media_type' => $v['mediaType'] ?? '',
                        'url' => $v['url'] ?? '',
                        'name' => $v['name'] ?? '',
                        'hash' => $v['blurhash'] ?? '',
                    ];
                }
                if (!empty($attachments)) {
                    $db->insert('attachments', $attachments);
                }
            }

            $db->update('activities', [
                'object_id' => $objectId, 'profile_id' => $profile['id']
            ], ['id' => $activityId]);

            if ($object->isReply() && $parentId) {
                $db->update('objects', ['replies[+]' => 1], ['id' => $parentId]);
            }
            $db->pdo->commit();
        } catch (\PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }
}