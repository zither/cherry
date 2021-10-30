<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\ActivityPub;
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

        $poll = [];
        if ($object->type === ActivityPub::OBJECT_TYPE_QUESTION) {
            $multiple = isset($rawActivity['object']['anyOf']) ? true : false;
            $choicesKey = $multiple ? 'anyOf' : 'oneOf';
            $choices = [];
            foreach ($rawActivity['object'][$choicesKey] as $v) {
                $choices[] = [
                    'type' => $v['type'],
                    'name' => $v['name'],
                    'count' => $v['replies']['totalItems'] ?? 0,
                ];
            }
            $poll = [
                'activity_id' => $activityId,
                'choices' => json_encode($choices, JSON_UNESCAPED_UNICODE),
                'end_time' => Time::UTCToLocalTime($rawActivity['object']['endTime']),
                'voters_count' => $rawActivity['object']['votersCount'] ?? 0,
                'multiple' => $multiple,
            ];
        }

        try {
            $db->pdo->beginTransaction();
            $tags = [];
            $emojis = [];
            foreach ($object->tag as $v) {
                if ($v['type'] === 'Hashtag') {
                    $tags[] = trim($v['name'], '#');
                } else if ($v['type'] === 'Emoji' && isset($v['icon']['url'])) {
                    $emojis[$v['name']] = sprintf(
                        '<img class="emoji" src="%s" alt="%s" referrerpolicy="no-referrer" />',
                        $v['icon']['url'],
                        $v['name']
                    );
                }
            }
            $content = $object->getStringAttribute('content');
            if (!empty($emojis)) {
                $content = str_replace(array_keys($emojis), array_values($emojis), $content);
            }
            $db->insert('objects', [
                'profile_id' => $profile['id'],
                'origin_id' => $originId,
                'parent_id' => $parentId,
                'raw_object_id' => $object->id,
                'type' => $object->type,
                'content' => $content,
                'summary' => $object->getStringAttribute('summary'),
                'url' => $object->getStringAttribute('url') ?: $object->id,
                'published' => Time::UTCToLocalTime($object->published),
                'is_local' => 0,
                'is_public' => $object->isPublic(),
                'is_boosted' => 0,
                'is_sensitive' => isset($object->sensitive) && $object->sensitive,
            ]);
            $objectId = $db->id();

            if (!empty($poll)) {
                $poll['object_id'] = $objectId;
                $db->insert('polls', $poll);
            }

            if (!empty($tags)) {
                $hashTags = [];
                foreach ($tags as $v) {
                    $hashTags[] = [
                        'term' => $v,
                        'object_id' => $objectId,
                        'profile_id' => $profile['id'],
                    ];
                }
                $db->insert('tags', $hashTags);
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