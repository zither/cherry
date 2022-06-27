<?php

namespace Cherry\Task;

use PDOException;
use Exception;
use RuntimeException;
use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\ActivityPub;
use Cherry\ActivityPub\ObjectType;
use Cherry\Helper\SignRequest;
use Cherry\Helper\Time;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class FetchObjectTask implements TaskInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function command(array $args)
    {
        $helper = $this->container->get(SignRequest::class);
        $request = new Request('GET', $args['id'], [
            'Accept' => 'application/activity+json',
            'Content-Type' => 'text/html',
        ]);
        $request = $helper->sign($request);
        $client = new Client();
        $response = $client->send($request);

        $statusCode = $response->getStatusCode();
        if ($statusCode === 401 || $statusCode === 404) {
            return [];
        }
        if ($statusCode >= 300) {
            throw new RuntimeException('Failed to fetch the object: ' . $args['id']);
        }
        $data = $response->getBody()->getContents();
        $objectArr = json_decode($data, true);
        if (empty($objectArr) || empty($objectArr['id'])) {
            throw new RuntimeException('Invalid object: ' . $args['id']);
        }

        return $this->process($objectArr);
    }

    public function process(array $args)
    {
        $db = $this->container->get(Medoo::class);
        $object = ObjectType::createFromArray($args);

        if (!empty($args['actor'])) {
            $actor = $args['actor'];
        } else if (!empty($object->attributedTo) && is_string($object->attributedTo)) {
            //@TODO The attributed entity might not be Actort
            $actor = $object->attributedTo;
        } else {
            throw new RuntimeException('Actor not found: ' . $args['id']);
        }

        $profile = $db->get('profiles', ['id'], ['actor' => $actor]);
        if (empty($profile)) {
            $subTask = new FetchProfileTask($this->container);
            $profile = $subTask->command(['actor' => $actor]);
        }
        $originId = 0;
        $parentId = 0;
        if ($object->isReply()) {
            $parent = $db->get('objects', ['id', 'origin_id', 'parent_id'], ['raw_object_id' => $object->inReplyTo]);
            if (empty($parent)) {
                // Just try one time to fetch parent object
                try {
                    $fetchParentTask = new FetchObjectTask($this->container);
                    $parent = $fetchParentTask->command(['id' => $object->inReplyTo]);
                } catch (Exception $e) {
                    $parent = [];
                }
            }
            if (!empty($parent)) {
                $originId = $parent['origin_id'] ?: $parent['id'];
                $parentId = $parent['id'];
            }
        }

        $poll = [];
        if ($object->type === ActivityPub::OBJECT_TYPE_QUESTION) {
            $multiple = isset($args['anyOf']) ? true : false;
            $choicesKey = $multiple ? 'anyOf' : 'oneOf';
            $choices = [];
            foreach ($args[$choicesKey] as $v) {
                $choices[] = [
                    'type' => $v['type'],
                    'name' => $v['name'],
                    'count' => $v['replies']['totalItems'] ?? 0,
                ];
            }
            $poll = [
                'activity_id' => 0,
                'choices' => json_encode($choices, JSON_UNESCAPED_UNICODE),
                // endTime may be null
                'end_time' => Time::UTCToLocalTime($args['endTime'] ?? 'now'),
                'voters_count' => $rawActivity['object']['votersCount'] ?? 0,
                'multiple' => $multiple,
                'is_closed' => empty($args['endTime']) ? 1 : 0,
            ];
        }

        try {
            $db->pdo->beginTransaction();
            $tags = [];
            $emojis = [];
            foreach ($object->tag as $v) {
                $tagType = $this->tagType($v);
                if ($tagType === 'Hashtag') {
                    $tags[] = trim($v['name'], '#');
                } else if ($tagType === 'Emoji' && isset($v['icon']['url'])) {
                    $emojis[$v['name']] = sprintf(
                        '<img class="emoji" src="%s" alt="%s" referrerpolicy="no-referrer" />',
                        $v['icon']['url'],
                        $v['name']
                    );
                }
            }
            $content = $object->getStringAttribute('content');
            if (empty($content)) {
                $content = $object->getStringAttribute('name');
            }
            if (!empty($emojis)) {
                $content = str_replace(array_keys($emojis), array_values($emojis), $content);
            }

            $unlisted = 0;
            if ($object->isReply() && empty($object->content) && !empty($object->name)) {
                $unlisted = 1;
            }

            $objectData = [
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
                'unlisted' => $unlisted
            ];
            $db->insert('objects', $objectData);
            $objectData['id'] = $objectId = $db->id();

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

            if ($object->isReply() && $parentId) {
                $db->update('objects', ['replies[+]' => 1], ['id' => $parentId]);
            }
            $db->pdo->commit();

            return $objectData;
        } catch (PDOException $e) {
            $db->pdo->rollBack();
            throw new FailedTaskException($e->getMessage());
        }
    }

    public function tagType(array $tag)
    {
        if (isset($tag['type'])) {
            return $tag['type'];
        }
        if (isset($tag['name']) && preg_match('/^#.+/', $tag['name'])) {
            return 'HashTag';
        }
        return 'Unknown';
    }
}
