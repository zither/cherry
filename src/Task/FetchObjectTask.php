<?php

namespace Cherry\Task;

use adrianfalleiro\TaskInterface;
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
        $db = $this->container->get(Medoo::class);
        $helper = $this->container->get(SignRequest::class);
        $request = new Request('GET', $args['id'], [
            'Accept' => 'application/activity+json',
            'Content-Type' => 'text/html',
        ]);
        $request = $helper->sign($request);

        $client = new Client();
        $response = $client->send($request);

        $data = $response->getBody()->getContents();
        $objectArr = json_decode($data, true);
        $object = ObjectType::createFromArray($objectArr);

        $profile = $db->get('profiles', ['id'], ['actor' => $object->attributedTo]);
        if (empty($profile)) {
            $fetchTask = new FetchProfileTask($this->container);
            $profile = $fetchTask->command(['actor' => $object->attributedTo]);
        }

        // 这里没有递归
        $originId = 0;
        $parentId = 0;
        if ($object->isReply()) {
            $parent = $db->get('objects', ['id', 'origin_id', 'parent_id'], ['raw_object_id' => $object->inReplyTo]);
            if (!empty($parent)) {
                $originId = $parent['origin_id'] ?: $parent['id'];
                $parentId = $parent['id'];
            }
        }

        $newObject = [
            'profile_id' => $profile['id'],
            'origin_id' => $originId,
            'parent_id' => $parentId,
            'raw_object_id' => $object->id,
            'type' => $object->type,
            'content' => $object->getStringAttribute('content'),
            'summary' => $object->getStringAttribute('summary'),
            'url' => $object->getStringAttribute('url') ?: $object->id,
            'published' => Time::UTCToLocalTime($object->published),
            'is_local' => 0, // local object always exists, so only remote object goes here
            'is_public' => $object->isPublic(),
            'is_sensitive' => isset($object->sensitive) && $object->sensitive,
        ];

        $db->insert('objects', $newObject);
        $newObject['id'] = $db->id();

        if (!empty($object->tag)) {
            $tags = [];
            foreach ($object->tag as $v) {
                if ($v['type'] !== 'Hashtag') {
                    continue;
                }
                $tags[] = [
                    'term' => trim($v['name'], '#'),
                    'object_id' => $newObject['id'],
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
                    'object_id' => $newObject['id'],
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

        return $newObject;
    }
}