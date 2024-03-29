<?php
namespace Cherry\Controller;

use Cherry\ActivityPub\ActivityPub;
use Cherry\ActivityPub\Context;
use Cherry\Task\TaskQueue;
use InvalidArgumentException;
use Cherry\ActivityPub\Activity;
use Cherry\ActivityPub\ObjectType;
use Cherry\ActivityPub\OrderedCollection;
use Cherry\Helper\SignRequest;
use Cherry\Helper\Time;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ApiController extends BaseController
{
    public function webFinger(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $queries = $request->getQueryParams();
        $resource = $queries['resource'] ?? '';
        $arr = explode(':', $resource);
        $profile = $this->adminProfile();
        if (count($arr) !== 2 && $arr[1] !== $profile['account']) {
            return $response->withStatus(404);
        }

        $webFinger = [
            'subject' => "acct:{$profile['account']}",
            'aliases' => [
                $profile['actor'],
            ],
            'links' => [
                [
                    'rel' => 'http://webfinger.net/rel/profile-page',
                    'type' => 'text/html',
                    'href' => $profile['url']
                ],
                [
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => $profile['actor']
                ],
                [
                    "rel" => "http://webfinger.net/rel/avatar",
                    "type" => "image/jpg",
                    "href" => $profile['avatar']
                ]
            ]
        ];

        return $this->json($response, $webFinger);
    }

    public function nodeInfo(ServerRequestInterface $request, ResponseInterface $response)
    {
        $settings = $this->container->make('settings');
        $nodeInfo = [
            'links' => [
                [
                    'ref' => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
                    'href' => sprintf('https://%s/nodeinfo/2.0.json', $settings['domain'])
                ],
                [
                    'ref' => 'http://nodeinfo.diaspora.software/ns/schema/2.1',
                    'href' => sprintf('https://%s/nodeinfo/2.1.json', $settings['domain'])
                ],
            ]
        ];
        return $this->json($response, $nodeInfo);
    }

    public function nodeInfoDetails(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $nodeInfoVersion = $args['version'] ?? "2.0";
        if ($nodeInfoVersion !== '2.0' && $nodeInfoVersion !== '2.1') {
            return $response->withStatus(404);
        }

        $software = [
            'name' => 'cherry',
            'version' => CHERRY_VERSION,
        ];
        if ($nodeInfoVersion === '2.1') {
            $software['repository'] = CHERRY_REPOSITORY;
        }
        $nodeInfo = [
            'version' => $nodeInfoVersion,
            'software' => $software,
            'protocols' => ['activitypub'],
            'usage' => [
                'users' => [
                    'total' => 1,
                    'activeMonth' => 1,
                    'activeHalfyear' => 1
                ]
            ],
            'openRegistration' => false,
            'services' => [
                'inbound' => [],
                'outbound' => []
            ],
            'metadata' => []
        ];
        return $this->json($response, $nodeInfo);
    }

    public function profile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $profile = $this->adminProfile(['*']);

        if (empty($args['name']) || $args['name'] !== $profile['preferred_name']) {
            return $response->withStatus(404);
        }

        $user = [
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
                'id' => $profile['public_key_id'],
                'owner' => $profile['actor'],
                'publicKeyPem' => $profile['public_key'],
                'type' => 'Key'
            ]
        ];
        $user = Context::set($user, Context::OPTION_ACTIVITY_STREAMS | Context::OPTION_SECURITY_V1);
        return $this->ldJson($response, $user);
    }

    public function inbox(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $rawBody = "{$request->getBody()}";
        $activity = json_decode($rawBody, true);
        if (empty($activity['id'])) {
            return $response->withStatus(400);
        }

        $db = $this->db();
        $activityId = $activity['id'];
        $exists = $db->get('activities', ['id'], ['activity_id' => $activityId]);
        if ($exists) {
            return $response->withStatus(202);
        }
        $activityType = Activity::createFromArray($activity);

        try {
            $helper = new SignRequest();
            $signatureData = json_encode(
                $helper->getHttpSignatureDataFromRequest($request),
                JSON_UNESCAPED_SLASHES
            );
        } catch (InvalidArgumentException $e) {
            $headers = $request->getHeaders();
            $headers['error'] = $e->getMessage();
            $signatureData = json_encode($headers, JSON_UNESCAPED_SLASHES);
        }
        $data = [
            'activity_id' => $activity['id'],
            'type' => $activity['type'],
            'raw' => $rawBody,
            'signature_data' => $signatureData,
            'published' => isset($activity['published']) ? Time::UTCToLocalTime($activity['published']) : Time::getLocalTime(),
            'is_local' => 0,
            'is_public' => $activityType->isPublic(),
        ];
        $db->insert('activities', $data);
        $lastId = $db->id();


        if (!$activityType->isActorAlias()) {
            $typesMap = [
                'follow' => 'FollowRequestTask',
                'accept' => 'FollowBeAcceptedTask',
                'reject' => 'FollowBeRejectedTask',
                'create' => 'CreateRequestTask',
                'announce' => 'AnnounceRequestTask',
                'update' => 'UpdateRequestTask',
                'like' => 'RemoteLikeTask',
                'undo' => 'RemoteUndoTask',
                'delete' => 'RemoteDeleteTask',
            ];
            $task = $typesMap[$activityType->lowerType()] ?? null;
            if ($task) {
                $taskData = [
                    'task' => $task,
                    'params' => ['activity_id' => $lastId],
                ];
                $this->container->get(TaskQueue::class)->queue($taskData);
            }
        }

        $statusCode = $activityType->type == ActivityPub::CREATE ? 201 : 202;
        $response = $response->withStatus($statusCode);

        return $response;
    }

    public function outbox(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $db = $this->db();
        $profile = $this->adminProfile(['id', 'actor', 'outbox']);
        $total = $db->count('activities', [
            'profile_id' => $profile['id'],
            'type' => [ActivityPub::CREATE, ActivityPub::ANNOUNCE],
            'is_local' => 1,
            'is_deleted' => 0,
        ]);
        $page = $this->getQueryParam($request, 'page');
        $collection = Context::set([
            'id' => "{$profile['outbox']}?page={$page}",
            'type' => 'OrderedCollectionPage',
            'totalItems' => $total,
            'orderedItems' => [],
        ]);

        if (empty($page)) {
            $collection = array_merge($collection, [
                'id' => $profile['outbox'],
                'type' => 'OrderedCollection',
                'first' => "{$profile['outbox']}?page=1",
            ]);
        } else {
            $size = 10;
            $offset = ($page - 1) * $size;
            $activities = $db->select('activities', '*', [
                'profile_id' => $profile['id'],
                'type' => [ActivityPub::CREATE, ActivityPub::ANNOUNCE],
                'is_local' => 1,
                'is_public' => 1,
                'is_deleted' => 0,
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => [$offset, $size]
            ]);
            $collection['partOf'] = $profile['outbox'];
            if (($page * $size) < $total && !empty($activities)) {
                $next = $page + 1;
                $collection['next'] = "{$profile['outbox']}?page={$next}";
            }

            $items = [];
            foreach ($activities as $v) {
                $activity = json_decode($v['raw'], true);
                unset($activity['@context']);
                $activity['object']['type'] = 'Note';
                $activity['object']['replies'] = [
                    'type' => 'OrderedCollection',
                    'totalItems' => 0,
                    'id' => "{$v['activity_id']}/replies",
                    'first' => "{$v['activity_id']}/replies?page=first",
                ];
                $activity['object']['likes'] = [
                    'type' => 'OrderedCollection',
                    'totalItems' => 0,
                    'id' => "{$v['activity_id']}/likes",
                    'first' => "{$v['activity_id']}/likes?page=first",
                ];
                $activity['object']['shares'] = [
                    'type' => 'OrderedCollection',
                    'totalItems' => 0,
                    'id' => "{$v['activity_id']}/shares",
                    'first' => "{$v['activity_id']}/shares?page=first",
                ];
                $items[] = $activity;
            }
            if (!empty($items)) {
                $collection['orderedItems'] = $items;
            }
        }

        return $this->ldJson($response, $collection);
    }

    public function objectInfo(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $publicId = $args['public_id'];
        if (empty($publicId)) {
            return $response->withStatus(404);
        }

        $db = $this->db();
        $settings = $this->container->make('settings', ['keys' => ['domain']]);
        $objectUrl = sprintf('https://%s/objects/%s', $settings['domain'], $publicId);
        $objectId = $db->get('objects', 'id', ['raw_object_id' => $objectUrl, 'is_local' => 1]);
        if (empty($objectId)) {
            return $response->withStatus(404);
        }

        $raw = $this->db()->get('activities', 'raw', ['object_id' => $objectId, 'type' => 'Create']);
        $activity = json_decode($raw, true);
        $object = $activity['object'];

        $objectType = ObjectType::createFromArray($object);
        if (!$objectType->isPublic()) {
            return $response->withStatus(401);
        }

        return $this->ldJson($response, $objectType->toArray());
    }

    public function activityInfo(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $publicId = $args['public_id'];
        if (empty($publicId)) {
            return $response->withStatus(404);
        }

        $db = $this->db();
        $settings = $this->container->make('settings', ['keys' => ['domain']]);
        $activityUrl = sprintf('https://%s/activities/%s', $settings['domain'], $publicId);
        $activity = $db->get('activities', '*', ['activity_id' => $activityUrl]);
        if (empty($activity)) {
            return $response->withStatus(404);
        }

        $rawActivity = json_decode($activity['raw'], true);
        $activityType = Activity::createFromArray($rawActivity);
        if (!in_array($activityType->type, [ActivityPub::CREATE, ActivityPub::ANNOUNCE])) {
            return $response->withStatus(404);
        }
        //@TODO check request authorization
        if (!$activityType->isPublic()) {
            return $response->withStatus(401);
        }

        return $this->ldJson($response, $rawActivity);
    }

    public function followers(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $profile = $this->adminProfile(['id', 'followers']);
        $count = $this->db()->count('followers');
        $collection = OrderedCollection::createFromArray([
            'id' => $profile['followers'],
            'totalItems' => $count,
            'orderedItems' => [],
        ]);
        return $this->ldJson($response, $collection->toArray());
    }

    public function following(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $profile = $this->adminProfile(['id', 'following']);
        $count = $this->db()->count('following');
        $collection = OrderedCollection::createFromArray([
            'id' => $profile['following'],
            'totalItems' => $count,
            'orderedItems' => [],
        ]);
        return $this->ldJson($response, $collection->toArray());
    }

    protected function json(ResponseInterface $response, array $body = []): ResponseInterface
    {
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($body, JSON_UNESCAPED_SLASHES));
        return $response;
    }

    protected function ldJson(ResponseInterface $response, array $body = []): ResponseInterface
    {
        $response = $response->withHeader(
            'Content-Type',
            'application/ld+json; profile="https://www.w3.org/ns/activitystreams"'
        );
        $response->getBody()->write(json_encode($body, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        return $response;
    }
}