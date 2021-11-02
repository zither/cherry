<?php
namespace Cherry\Controller;

use InvalidArgumentException;
use Cherry\ActivityPub\Activity;
use Cherry\ActivityPub\ObjectType;
use Cherry\ActivityPub\OrderedCollection;
use Cherry\Helper\SignRequest;
use Cherry\Helper\Time;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ApiController
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function webFinger(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $queries = $request->getQueryParams();
        $resource = $queries['resource'] ?? '';
        $arr = explode(':', $resource);
        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', '*', ['id' => 1]);
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
        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', '*', ['id' => 1]);
        $user = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                "https://w3id.org/security/v1",
            ],
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
        return $this->ldJson($response, $user);
    }

    public function inbox(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $rawBody = "{$request->getBody()}";
        $activity = json_decode($rawBody, true);
        if (empty($activity['id'])) {
            return $response->withStatus(400);
        }

        $db = $this->container->get(Medoo::class);
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
                $db->insert('tasks', [
                    'task' => $task,
                    'params' => json_encode(['activity_id' => $lastId]),
                ]);
            }
        }

        $statusCode = $activityType->lowerType() == 'create' ? 201 : 202;
        $response = $response->withStatus($statusCode);

        return $response;
    }

    public function outbox(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', ['id', 'actor', 'outbox'], ['id' => 1]);
        $total = $db->count('activities', [
            'profile_id' => $profile['id'],
            'type' => ['Create', 'Announce'],
            'is_local' => 1,
            'is_deleted' => 0,
        ]);
        $page = $this->getQueryParam($request, 'page');
        $collection = [
            'context' => [
                "https://www.w3.org/ns/activitystreams",
            ],
            'id' => "{$profile['outbox']}?page={$page}",
            'type' => 'OrderedCollectionPage',
            'totalItems' => $total,
            'orderedItems' => [],
        ];

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
                'type' => ['Create', 'Announce'],
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
        $snowflakeId = $args['snowflake_id'];
        if (empty($snowflakeId)) {
            return $response->withStatus(404);
        }

        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', ['id', 'outbox'], ['id' => 1]);
        $activityUrl = "{$profile['outbox']}/$snowflakeId";
        $activity = $db->get('activities', '*', ['activity_id' => $activityUrl]);
        if (empty($activity) || $activity['type'] !== 'Create') {
            return $response->withStatus(404);
        }

        $rawActivity = json_decode($activity['raw'], true);
        $object = $rawActivity['object'];

        $objectType = ObjectType::createFromArray($object);
        if (!$objectType->isPublic()) {
            return $response->withStatus(401);
        }

        return $this->ldJson($response, $objectType->toArray());
    }

    public function activityInfo(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $snowflakeId = $args['snowflake_id'];
        if (empty($snowflakeId)) {
            return $response->withStatus(404);
        }

        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', ['id', 'outbox'], ['id' => 1]);
        $activityUrl = "{$profile['outbox']}/$snowflakeId";
        $activity = $db->get('activities', '*', ['activity_id' => $activityUrl]);
        if (empty($activity)) {
            return $response->withStatus(404);
        }

        $rawActivity = json_decode($activity['raw'], true);
        $activityType = Activity::createFromArray($rawActivity);
        if (!in_array($activityType->lowerType(), ['create', 'announce'])) {
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
        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', ['id', 'followers'], ['id' => 1]);
        $count = $db->count('followers');
        $collection = OrderedCollection::createFromArray([
            'id' => $profile['followers'],
            'totalItems' => $count,
            'orderedItems' => [],
        ]);
        return $this->ldJson($response, $collection->toArray());
    }

    public function following(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', ['id', 'following'], ['id' => 1]);
        $count = $db->count('following');
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

    protected function getPostParam(ServerRequestInterface $request, string $key, $default = null)
    {
        $params = $request->getParsedBody();
        return $params[$key] ?? $default;
    }

    protected function getQueryParam(ServerRequestInterface $request, string $key, $default = null)
    {
        $queries = $request->getQueryParams();
        return $queries[$key] ?? $default;
    }
}