<?php
namespace Cherry\Controller;

use Cherry\FlashMessage;
use Cherry\Helper\SignRequest;
use Cherry\Helper\Time;
use Cherry\Markdown;
use Cherry\Session\SessionInterface;
use Godruoyi\Snowflake\Snowflake;
use League\Plates\Engine;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\RouteCollectorInterface;
use GuzzleHttp\Cookie\SetCookie;
use Exception;
use InvalidArgumentException;

class IndexController
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function home(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $header = $request->getHeaderLine('Accept');
        if (strpos($header, '+json') !== false) {
            /** @var RouteCollectorInterface $router */
            $router = $this->container->get('router');
            return $router->getNamedRoute('api_profile')->run($request);
        }

        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', [
            'actor',
            'name',
            'preferred_name',
            'account',
            'url',
            'avatar',
            'summary',
        ], ['id' => 1]);

        $pageSize = 10;
        $index = $this->getQueryParam($request, 'index', null);

        $defaultConditions = [
            'activities.object_id[!]' => 0,
            'activities.type' => ['Create', 'Announce'],
            'activities.is_local' => 1,
            'activities.is_deleted' => 0,
            'LIMIT' => $pageSize,
            'ORDER' => ['activities.id' => 'DESC'],
        ];
        if (!$this->isLogin($request)) {
            $defaultConditions['activities.is_public'] = 1;
        }
        if ($index) {
            $conditions = array_merge($defaultConditions, ['activities.id[<=]' => $index]);
        } else  {
            $conditions = $defaultConditions;
        }
        $blogs = $db->select('activities',  [
            '[>]profiles' => ['profile_id' => 'id'],
            '[>]objects' => ['object_id' => 'id'],
        ], [
            'activities.id',
            'activities.object_id',
            'activities.activity_id',
            'activities.profile_id(activity_profile_id)',
            'activities.published',
            'activities.type(activity_type)',
            'profiles.actor(activity_actor)',
            'profiles.name(activity_name)',
            'profiles.preferred_name(activity_preferred_name)',
            'profiles.url(activity_profile_url)',
            'profiles.avatar(activity_avatar)',
            'objects.profile_id',
            'objects.type',
            'objects.content',
            'objects.summary',
            'objects.url',
            'objects.parent_id',
            'objects.likes',
            'objects.replies',
            'objects.shares',
            'objects.is_local',
            'objects.is_public',
            'objects.is_sensitive',
            'objects.is_liked',
            'objects.is_boosted',
        ], $conditions);

        $objectProfileIds = [];
        $objectIds = [];
        $parentIds = [];
        foreach ($blogs as $v) {
            if ($v['profile_id']) {
                $objectProfileIds[] = $v['profile_id'];
            }
            if ($v['object_id']) {
                $objectIds[] = $v['object_id'];
            }
            if ($v['parent_id']) {
                $parentIds[] = $v['parent_id'];
            }
        }

        $objectProfiles = [];
        if (!empty($objectProfileIds)) {
            $profiles = $db->select('profiles', [
                'id',
                'actor',
                'name',
                'preferred_name',
                'url',
                'avatar',
                'account'
            ], ['id' => $objectProfileIds]);
            foreach ($profiles as $v) {
                $objectProfiles[$v['id']] = $v;
            }
        }

        $parentProfiles = [];
        if (!empty($parentIds)) {
            $tmpProfiles = $db->select('objects', [
                '[>]profiles' => ['profile_id' => 'id'],
            ], [
                'objects.id(object_id)',
                'objects.raw_object_id',
                'objects.profile_id(id)',
                'profiles.actor',
                'profiles.name',
                'profiles.preferred_name',
                'profiles.url',
                'profiles.avatar',
                'profiles.account'
            ],[
                'objects.id' => $parentIds,
            ]);
            foreach ($tmpProfiles as $v) {
                $parentProfiles[$v['object_id']] = $v;
            }
        }


        $objectAttachments = [];
        if (!empty($objectIds)) {
            $attachments = $db->select('attachments', '*', ['object_id' => $objectIds]);
            foreach ($attachments as $v) {
                if (!isset($objectAttachments[$v['object_id']])) {
                    $objectAttachments[$v['object_id']] = [];
                }
                $objectAttachments[$v['object_id']][] = $v;
            }
        }

        foreach ($blogs as &$v) {
            if (empty($v['profile_id'])) {
                continue;
            }
            $v['date'] = Time::getLocalTime($v['published']);
            $objectInfo = $objectProfiles[$v['profile_id']];
            $v['profile_id'] = $objectInfo['id'];
            $v['actor'] = $objectInfo['actor'];
            $v['preferred_name'] = $objectInfo['preferred_name'];
            $v['name'] = $objectInfo['name'];
            $v['content'] = $this->stripTags($v['content']);
            $v['profile_url'] = $objectInfo['url'];
            $v['avatar'] = $objectInfo['avatar'];
            $v['account'] = "@{$objectInfo['account']}";
            $v['show_boosted'] = $v['activity_type'] === 'Announce';
            if ($v['is_local']) {
                preg_match('#\d{18}#', $v['activity_id'], $matches);
                $v['snowflake_id'] =  $matches[0];
            }
            if ($v['parent_id']) {
                $v['parent_profile'] = $parentProfiles[$v['parent_id']] ?? [];
            }
            $v['attachments'] = $objectAttachments[$v['object_id']] ?? [];
            unset($v);
        }

        $first = $blogs[0]['id'] ?? 0;
        $count = count($blogs);
        $last = $blogs[$count - 1]['id'] ?? 0;

        $prevConditions = array_merge($defaultConditions, [
            'activities.id[>]' => $first,
            'LIMIT' => $pageSize,
            'ORDER' => [
                'activities.id' => 'ASC',
            ]
        ]);


        $prevIndexes = $db->select('activities', ['id', 'published'], $prevConditions);
        $prevIndexes =  array_reverse($prevIndexes);
        $prevIndex = empty($prevIndexes) ? 0 : $prevIndexes[0]['id'];
        $nextConditions = array_merge($defaultConditions, ['activities.id[<]' => $last, 'LIMIT' => 1]);
        $nextIndex = $db->get('activities', 'id', $nextConditions);

        $blogsCount = $db->count('activities', [
            'is_local' => 1,
            'is_deleted' => 0,
            'type' => ['Create', 'Announce']
        ]);
        $followersCount = $db->count('followers', ['status' => 1]);
        $followingCount = $db->count('following', ['status' => 1]);
        $notificationsCount = $db->count('notifications', ['viewed' => 0]);
        $counts = [
            'objects' => $blogsCount,
            'following' => $followingCount,
            'followers' => $followersCount,
        ];

        $template = $this->container->get(Engine::class);
        $view = $template->render('index', [
            'profile' => $profile,
            'blogs' => $blogs,
            'counts' => $counts,
            'is_admin' => $this->isLogin($request),
            'notifications' => $notificationsCount,
            'prev' => $prevIndex,
            'next' => $nextIndex,
        ]);
        $response->getBody()->write($view);
        return $response;
    }

    public function timeline(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $pageSize = 10;
        $index = $this->getQueryParam($request, 'index');
        $pid = $this->getQueryParam($request, 'pid');
        $db = $this->container->get(Medoo::class);
        $defaultConditions = [
            'activities.object_id[!]' => 0,
            'activities.type' => ['Create', 'Announce'],
            'activities.is_local' => 0,
            'activities.is_deleted' => 0,
            'LIMIT' => $pageSize,
            'ORDER' => ['activities.published' => 'DESC'],
        ];
        if ($pid) {
            $defaultConditions['activities.profile_id'] = $pid;
        }

        if ($index) {
            $conditions = array_merge($defaultConditions, ['activities.published[<=]' => base64_decode($index)]);
        } else  {
            $conditions = $defaultConditions;
        }
        $blogs = $db->select('activities',  [
            '[>]profiles' => ['profile_id' => 'id'],
            '[>]objects' => ['object_id' => 'id'],
        ], [
            'activities.id',
            'activities.object_id',
            'activities.activity_id',
            'activities.profile_id(activity_profile_id)',
            'activities.published',
            'activities.type(activity_type)',
            'profiles.actor(activity_actor)',
            'profiles.name(activity_name)',
            'profiles.preferred_name(activity_preferred_name)',
            'profiles.url(activity_profile_url)',
            'profiles.avatar(activity_avatar)',
            'objects.profile_id',
            'objects.type',
            'objects.content',
            'objects.summary',
            'objects.url',
            'objects.parent_id',
            'objects.likes',
            'objects.replies',
            'objects.shares',
            'objects.is_local',
            'objects.is_public',
            'objects.is_sensitive',
            'objects.is_liked',
            'objects.is_boosted',
        ], $conditions);


        $objectProfileIds = [];
        $objectIds = [];
        $parentIds = [];
        foreach ($blogs as $v) {
            if ($v['profile_id']) {
                $objectProfileIds[] = $v['profile_id'];
            }
            if ($v['object_id']) {
                $objectIds[] = $v['object_id'];
            }
            if ($v['parent_id']) {
                $parentIds[] = $v['parent_id'];
            }
        }

        $objectProfiles = [];
        if (!empty($objectProfileIds)) {
            $profiles = $db->select('profiles', [
                'id',
                'actor',
                'name',
                'preferred_name',
                'url',
                'avatar',
                'account'
            ], ['id' => $objectProfileIds]);
            foreach ($profiles as $v) {
                $objectProfiles[$v['id']] = $v;
            }
        }

        $parentProfiles = [];
        if (!empty($parentIds)) {
            $tmpProfiles = $db->select('objects', [
                '[>]profiles' => ['profile_id' => 'id'],
            ], [
                'objects.id(object_id)',
                'objects.raw_object_id',
                'objects.profile_id(id)',
                'profiles.actor',
                'profiles.name',
                'profiles.preferred_name',
                'profiles.url',
                'profiles.avatar',
                'profiles.account'
            ],[
                'objects.id' => $parentIds,
            ]);
            foreach ($tmpProfiles as $v) {
                $parentProfiles[$v['object_id']] = $v;
            }
        }


        $objectAttachments = [];
        if (!empty($objectIds)) {
            $attachments = $db->select('attachments', '*', ['object_id' => $objectIds]);
            foreach ($attachments as $v) {
                if (!isset($objectAttachments[$v['object_id']])) {
                    $objectAttachments[$v['object_id']] = [];
                }
                $objectAttachments[$v['object_id']][] = $v;
            }
        }

        foreach ($blogs as &$v) {
            if (empty($v['profile_id'])) {
                continue;
            }
            $v['date'] = Time::getLocalTime($v['published']);
            $objectInfo = $objectProfiles[$v['profile_id']];
            $v['profile_id'] = $objectInfo['id'];
            $v['actor'] = $objectInfo['actor'];
            $v['preferred_name'] = $objectInfo['preferred_name'];
            $v['name'] = $objectInfo['name'];
            $v['content'] = $this->stripTags($v['content']);
            $v['profile_url'] = $objectInfo['url'];
            $v['avatar'] = $objectInfo['avatar'];
            $v['account'] = "@{$objectInfo['account']}";
            $v['show_boosted'] = $v['activity_type'] === 'Announce';
            if ($v['is_local']) {
                preg_match('#\d{18}#', $v['activity_id'], $matches);
                $v['snowflake_id'] =  $matches[0];
            }
            if ($v['parent_id']) {
                $v['parent_profile'] = $parentProfiles[$v['parent_id']] ?? [];
            }
            $v['attachments'] = $objectAttachments[$v['object_id']] ?? [];
            unset($v);
        }

        if (!empty($blogs)) {
            $first = $blogs[0]['published'];
            $count = count($blogs);
            $last = $blogs[$count - 1]['published'];

            $prevConditions = array_merge($defaultConditions, [
                'activities.published[>]' => $first,
                'LIMIT' => $pageSize,
                'ORDER' => [
                    'activities.published' => 'ASC',
                ]
            ]);

            $prevIndexes = $db->select('activities', ['id', 'published'], $prevConditions);
            $prevIndexes = array_reverse($prevIndexes);
            $prevIndex = empty($prevIndexes) ? 0 : $prevIndexes[0]['published'];
            $nextConditions = array_merge($defaultConditions, ['activities.published[<]' => $last, 'LIMIT' => 1]);
            $nextIndex = $db->get('activities', 'published', $nextConditions);

            $prevArgs = $prevIndex ? ['index' => base64_encode($prevIndex)] : [];
            $nextArgs = $nextIndex ? ['index' => base64_encode($nextIndex)] : [];
            if ($pid) {
                $prevArgs['pid'] = $pid;
                $nextArgs['pid'] = $pid;
            }
        }

        $template = $this->container->get(Engine::class);
        $view = $template->render('timeline', [
            'blogs' => $blogs,
            'prev' => empty($prevArgs['index']) ? null : http_build_query($prevArgs),
            'next' => empty($nextArgs['index']) ? null : http_build_query($nextArgs),
        ]);
        $response->getBody()->write($view);
        return $response;
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($this->isLogin($request)) {
            return $response->withStatus('302')->withHeader('location', '/timeline');
        }
        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', [
            'actor',
            'name',
            'preferred_name',
            'account',
            'url',
            'avatar',
            'summary',
        ], ['id' => 1]);

        return $this->render($response, 'login', ['profile' => $profile]);
    }

    public function verifyPassword(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $password = $this->getPostParam($request, 'password');
            if (!$password) {
                throw new \Exception('Password required');
            }
            $db = $this->container->get(Medoo::class);
            $admin = $db->get('settings', '*', ['id' => 1]);
            if (!password_verify($password, $admin['password'])) {
                throw new \Exception('Invalid password');
            }
            $session = $this->session($request);
            $session['is_admin'] = true;
            $cookie = new SetCookie([
                'Name' => $session->getName(),
                'Value' => $session->getId(),
                'Expires' => time() + 3600 * 24 * 365,
                'HttpOnly' => true,
            ]);
            $response = $response->withHeader('Set-Cookie', (string)$cookie);
            $redirect = '/editor';
        } catch (\Exception $e) {
            $redirect = '/login';
        }
        return $response->withStatus('302')->withHeader('location', $redirect);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $session = $this->session($request);
        $cookie = new SetCookie(['Name' => $session->getName()]);
        $cookie->setExpires(time() - 3600);
        $response = $response->withHeader('Set-Cookie', (string)$cookie);
        return $response->withStatus('302')->withHeader('location', '/');
    }

    public function editor(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->render($response, 'editor');
    }

    public function createPost(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $content = $this->getPostParam($request, 'content');
        if (empty($content)) {
            return $response->withStatus('302')->withHeader('location', '/editor');
        }
        $scope = $this->getPostParam($request, 'scope');

        // 回复嘟文的编号
        $inReplyTo = $this->getPostParam($request, 'in_reply_to');

        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', '*', ['id' => 1]);
        $domain = parse_url($profile['actor'], PHP_URL_HOST);

        // 嘟文
        $markdown = $this->container->get(Markdown::class);
        $markdown->setTagHost($domain);
        $parsedContent = $markdown->text($content);
        $tags = $markdown->hashTags();

        // 获取自身和目标对象相关信息
        if (!empty($inReplyTo)) {
            $replyObject = $db->get('objects', [
                'id',
                'raw_object_id',
                'profile_id',
                'origin_id',
                'is_local',
            ], ['id' => $inReplyTo]);
            //$replyInbox = $db->get('profiles', 'inbox', ['id' => $replyObject['profile_id']]);
            $replyProfile = $db->get('profiles', ['actor', 'inbox', 'account'], [
                'id' => $replyObject['profile_id']
            ]);
            $replyActor = $replyProfile['actor'];
        }

        // 新嘟文 Object 信息
        $snowflake = $this->container->get(Snowflake::class);
        $objectId = $snowflake->id();
        $published = Time::ISO8601();
        $object = [
            'id' => "{$profile['outbox']}/$objectId/object",
            'url' => "https://$domain/notes/$objectId",
            'type' => 'Note',
            'attributedTo' => $profile['actor'],
            'summary' => '',
            'content' => $parsedContent,
            'published' => $published,
        ];

        $audiences = ['to' => [], 'cc' => []];
        switch ((int)$scope) {
            // 公开消息
            case 1:
                $audiences['to'][] = "https://www.w3.org/ns/activitystreams#Public";
                $audiences['cc'][] = $profile['followers'];
                if (!empty($replyActor)) {
                    $audiences['cc'][] = $replyActor;
                }
                break;
            // 不公开消息
            case 2:
                $audiences['to'][] = $profile['followers'];
                $audiences['cc'][] = "https://www.w3.org/ns/activitystreams#Public";
                if (!empty($replyActor)) {
                    $audiences['cc'][] = $replyActor;
                }
                break;
            // 仅关注消息
            case 3:
                $audiences['to'][] = $profile['followers'];
                if (!empty($replyActor)) {
                    $audiences['cc'][] = $replyActor;
                }
                break;
            // 私信
            default:
                if (!empty($replyActor)) {
                    $audiences['to'] = $replyActor;
                }
        }
        if (!empty($replyActor)) {
            $mentions = ['tag' => [
                [
                    'type' => 'Mention',
                    'href' => $replyProfile['actor'],
                    'name' => '@' . $replyProfile['account'],
                ]
            ]];
            $object = array_merge($object, ['inReplyTo' => $replyObject['raw_object_id']], $audiences, $mentions);
        } else {
            $object = array_merge($object, $audiences);
        }

        // 新 Activity 信息
        $activity = array_merge([
            "@context" => "https://www.w3.org/ns/activitystreams",
            'id' => "https://$domain/outbox/$objectId",
            'type' => 'Create',
            'actor' => $profile['actor'],
            'published' => $published,
        ], $audiences);

        try {
            $db->pdo->beginTransaction();

            // 保存新 Object
            $db->insert('objects', [
                'type' => $object['type'],
                'profile_id' => 1,
                'raw_object_id' => $object['id'],
                'content' => $object['content'],
                'summary' => $object['summary'] ?? '',
                'url' => $object['url'],
                'published' => Time::utc($object['published']),
                'is_local' => 1,
                'is_public' => $scope < 3 ? 1 : 0,
                'origin_id' => $replyObject['origin_id'] ?? 0,
                'parent_id' => $replyObject['id'] ?? 0,
            ]);
            $objectId = $db->id();

            if (!empty($tags)) {
                $hashTags = [];
                $terms = [];
                foreach ($tags as $v) {
                    $terms[] = [
                        'term' => $v,
                        'profile_id' => 1,
                        'object_id' => $objectId,
                    ];
                    $hashTags[] = [
                        'type' => 'Hashtag',
                        'name' => "#$v",
                        'href' => "{$profile['actor']}/tags/$v",
                    ];
                }
                $db->insert('tags', $terms);

                // 添加 tag 信息
                $object['tag'] = $hashTags;
            }

            $activity['object'] = $object;

            // 保存新 Activity
            $db->insert('activities', [
                'activity_id' => $activity['id'],
                'profile_id' => 1,
                'object_id' => $objectId,
                'type' => 'Create',
                'raw' => json_encode($activity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'published' => date('Y-m-d H:i:s'),
                'is_local' => 1,
                'is_public' => $scope < 3 ? 1 : 0,
            ]);
            $activityId = $db->id();

            // 如果回复的是本地嘟文，更新 interactions　相关内容
            if (!empty($replyObject['is_local'])) {
                $db->update('objects', [
                    'replies[+]' => 1,
                ], ['id' => $replyObject['id']]);
                $db->insert('interactions', [
                    'activity_id' => $activityId,
                    'object_id' => $replyObject['id'],
                    'profile_id' => $profile['id'],
                    'type' => 3, // replies
                    'published' => Time::utc($activity['published']),
                ]);
            }

            // 添加推送任务
            if (!empty($audiences['to']) || !empty($audiences['cc'])) {
                $task = [
                    'task' => 'DeliverActivityTask',
                    'params' => json_encode(['activity_id' => $activityId], JSON_UNESCAPED_SLASHES),
                    'priority' => 140,
                ];
                $db->insert('tasks', $task);
            }

            $db->pdo->commit();

            return $response->withStatus('302')->withHeader('location', '/');
        } catch (Exception $e) {
            $db->pdo->rollBack();
            return $this->redirectBack($request, $response);
        }
    }

    public function deletePost(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $snowflakeId = $args['snowflake_id'];
        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', ['id', 'outbox'], ['id' => 1]);
        $outboxId = sprintf('%s/%s', $profile['outbox'], $snowflakeId);
        $activity = $db->get('activities', ['id', 'object_id'], ['activity_id' => $outboxId]);

        // 标记为已删除
        $db->update('activities', ['is_deleted' => 1], ['id' => $activity['id']]);
        // 删除嘟文
        $db->delete('objects', ['id' => $activity['object_id']]);
        // 删除互动数据
        $db->delete('interactions', ['object_id' => $activity['object_id']]);
        // 删除标签
        $db->delete('tags', ['object_id' => $activity['object_id']]);

        $db->insert('tasks', [
            'task' => 'DeleteActivityTask',
            'params' => json_encode(['activity_id' => $activity['id']], JSON_UNESCAPED_SLASHES),
            'priority' => 140,
        ]);

        return $response->withStatus('302')->withHeader('location', '/');
    }

    public function note(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $snowflakeId = $args['snowflake_id'];
        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', ['id', 'outbox'], ['id' => 1]);
        $outboxId = sprintf('%s/%s', $profile['outbox'], $snowflakeId);
        $activity = $db->get('activities', ['id', 'object_id'], ['activity_id' => $outboxId]);

        if (empty($activity)) {
            throw new HttpNotFoundException($request);
        }

        $notes = $db->select('objects', [
            '[>]profiles' => ['profile_id' => 'id'],
        ], [
            'objects.id',
            'objects.raw_object_id',
            'objects.profile_id',
            'objects.type',
            'objects.content',
            'objects.summary',
            'objects.url',
            'objects.likes',
            'objects.replies',
            'objects.shares',
            'objects.published',
            'objects.is_local',
            'objects.is_public',
            'objects.is_boosted',
            'objects.is_sensitive',
            'profiles.actor',
            'profiles.name',
            'profiles.preferred_name',
            'profiles.url(profile_url)',
            'profiles.avatar',
            'profiles.account',
        ], [
            'OR' => [
                'objects.id' => $activity['object_id'],
                'objects.parent_id' => $activity['object_id'],
                'objects.origin_id' => $activity['object_id'],
            ],
            'ORDER' => ['published' => 'ASC']
        ]);

        $note = null;
        $replies = [];
        foreach ($notes as $v) {
            $v['date'] = Time::getLocalTime($v['published']);
            if ($v['is_local']) {
                preg_match('#\d{18}#', $v['raw_object_id'], $matches);
                $v['snowflake_id'] =  $matches[0];
            }
            if ($v['id'] == $activity['object_id']) {
                $note = $v;
            } else {
                $replies[] = $v;
            }
        }

        $interactions = $db->select('interactions', [
            '[>]profiles' => ['profile_id' => 'id']
        ], [
            'interactions.id',
            'interactions.profile_id',
            'interactions.type',
            'profiles.url',
            'profiles.avatar',
            'profiles.name',
            'profiles.preferred_name',
        ], [
            'interactions.object_id' => $activity['object_id']
        ]);

        foreach ($interactions as &$v) {
            if (empty($v['name'])) {
                 $v['name'] = $v['preferred_name'];
            }
            $v['title'] = $v['type']  == 1 ? $v['name'] . '喜欢' : $v['name'] . '转嘟';
        }

        return $this->render($response, 'note', [
            'note' => $note,
            'interactions' => $interactions,
            'replies' => $replies,
            'is_admin' => $this->isLogin($request),
        ]);
    }

    public function replyTo(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $objectId = $args['object_id'];
        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', '*', ['id' => 1]);
        $object = $db->get('objects', [
            '[>]profiles' => ['profile_id' => 'id'],
        ], [
            'objects.id',
            'objects.raw_object_id',
            'objects.profile_id',
            'objects.type',
            'objects.content',
            'objects.summary',
            'objects.url',
            'objects.likes',
            'objects.replies',
            'objects.shares',
            'objects.published',
            'objects.is_local',
            'objects.is_public',
            'objects.is_boosted',
            'objects.is_liked',
            'objects.is_sensitive',
            'profiles.actor',
            'profiles.name',
            'profiles.preferred_name',
            'profiles.url(profile_url)',
            'profiles.avatar',
            'profiles.account',
        ], [
            'objects.id' => $objectId,
        ]);

        $object['date'] = Time::getLocalTime($object['published']);
        if ($object['is_local']) {
            preg_match('#\d{18}#', $object['raw_object_id'], $matches);
            $object['snowflake_id'] =  $matches[0];
        }
        $object['show_boosted'] = false;

        $at = "[@{$object['preferred_name']}]({$object['profile_url']}) ";

        return $this->render($response, 'reply', [
            'note' => $object,
            'profile' => $profile,
            'at' => $at,
            'is_admin' => $this->isLogin($request),
        ]);
    }

    public function sendFollow(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $account = $this->getPostParam($request, 'account');
            $account = trim($account);
            if (empty($account)) {
                throw new InvalidArgumentException('Account required');
            }
            if (strpos($account, '@') === 0) {
                $account = substr($account, 1);
            }
            $accountArr = explode('@', $account);
            if (count($accountArr) !== 2) {
                throw new InvalidArgumentException('Invalid Account');
            }
            $db = $this->container->get(Medoo::class);
            $task = [
                'task' => 'FollowTask',
                'params' => json_encode(['account' => $account], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'priority' => 140,
            ];
            $db->insert('tasks', $task);

            $this->flash($request)->success('Follow Request Sent!');
        } catch (Exception $e) {
            $this->flash($request)->error($e->getMessage());
        }
        return $response->withStatus('302')->withHeader('location', '/web/following');
    }

    public function notifications(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $db = $this->container->get(Medoo::class);
        $notifications = $db->select('notifications(n)', [
            '[>]profiles(p)' => ['n.profile_id' => 'id'],
            '[>]activities(a)' => ['n.activity_id' => 'id'],
        ], [
            'n.id',
            'n.profile_id',
            'n.activity_id',
            'n.follower_id',
            'n.type',
            'n.viewed',
            'n.status',
            'n.actor',
            'p.avatar',
            'p.name',
            'p.preferred_name',
            'p.url',
            'a.raw',
            'a.published',
        ], [
            'LIMIT' => 10,
            'ORDER' => ['id' => 'DESC']
        ]);
        foreach ($notifications as &$v) {
            if (empty($v['raw'])) {
                continue;
            }
            $v['raw'] = json_decode($v['raw'], true);
            $v['published'] = Time::getLocalTime($v['published']);
        }

        return $this->render($response, 'notifications', ['notifications' => $notifications]);
    }

    public function handleFollowRequest(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $action = $this->getQueryParam($request, 'action');
            $notificationId = $args['notification_id'];
            if (empty($action) || empty($notificationId)) {
                throw new \InvalidArgumentException('Both action and notification id required');
            }
            $db = $this->container->get(Medoo::class);
            $notification = $db->get('notifications', '*', ['id' => $notificationId]);

            $params = json_encode([
                'activity_id' => $notification['activity_id'],
                'profile_id' => $notification['profile_id'],
            ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

            if ($action === 'accept') {
                $db->insert('tasks', [
                    'task' => 'AcceptFollowTask',
                    'params' => $params,
                    'priority' => 140,
                ]);
                $db->update('notifications', ['status' => 1, 'viewed' => 1], ['id' => $notificationId]);
            } else {
                if ($action === 'reject') {
                    $db->insert('tasks', [
                        'task' => 'RejectFollowTask',
                        'params' => $params,
                        'priority' => 140,
                    ]);
                }
                $db->delete('notifications', ['id' => $notificationId]);
            }
        } catch (\Exception $e) {
            // pass
        }
        return $response->withStatus('302')->withHeader('location', '/notifications');
    }

    public function liked(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $objectId = $args['object_id'];
        $db = $this->container->get(Medoo::class);
        $object = $db->get('objects', ['id', 'is_liked'], ['id' => $objectId]);
        $liked = $object['is_liked'] ? 0 : 1;
        $db->update('objects', ['is_liked' => $liked], ['id' => $objectId]);
        if ($liked) {
            $db->insert('tasks', [
                'task' => 'LocalInteractiveTask',
                'params' => json_encode(['object_id' => $objectId, 'type' => 'Like'], JSON_UNESCAPED_SLASHES),
                'priority' => 140,
            ]);
        } else if ($object['is_liked']) {
            $interaction = $db->get('interactions', ['activity_id'], ['profile_id' => 1, 'object_id' => $objectId]);
            $db->insert('tasks', [
                'task' => 'LocalUndoTask',
                'params' => json_encode(['activity_id' => $interaction['activity_id']], JSON_UNESCAPED_SLASHES),
                'priority' => 140,
            ]);
        }
        $referer = $request->getHeaderLine('Referer');
        $redirect = empty($referer) ? '/' : $referer;
        return $response->withStatus('302')->withHeader('location', $redirect);
    }

    public function boosted(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $objectId = $args['object_id'];
        $db = $this->container->get(Medoo::class);
        $object = $db->get('objects', ['id', 'is_boosted', 'is_public'], ['id' => $objectId]);
        if (!$object['is_public']) {
            // 检查是否运行转载
            goto REDIRECT_BACK;
        }

        $toBoost = $object['is_boosted'] ? 0 : 1;
        $db->update('objects', ['is_boosted' => $toBoost], ['id' => $objectId]);
        if ($toBoost) {
            $db->insert('tasks', [
                'task' => 'LocalInteractiveTask',
                'params' => json_encode(['object_id' => $objectId, 'type' => 'Announce'], JSON_UNESCAPED_SLASHES),
                'priority' => 140,
            ]);
        } else if ($object['is_boosted']) {
            $interaction = $db->get('interactions', ['activity_id'], ['profile_id' => 1, 'object_id' => $objectId]);
            $db->insert('tasks', [
                'task' => 'LocalUndoTask',
                'params' => json_encode(['activity_id' => $interaction['activity_id']], JSON_UNESCAPED_SLASHES),
                'priority' => 140,
            ]);
        }

        REDIRECT_BACK:
        $referer = $request->getHeaderLine('Referer');
        $redirect = empty($referer) ? '/' : $referer;
        return $response->withStatus('302')->withHeader('location', $redirect);
    }

    public function test(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $helper = new SignRequest();
        $signature = sprintf(
            'keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date digest content-type",signature="%s"',
            'https://chokecherry.cc#main-key',
            'sdfsfsfsfsffff'
        );
        $request = $request->withHeader('Signature', $signature);
        $request = $request->withHeader('date', date('Y-m-d H:i:s'));
        $request = $request->withHeader('digest', date('Y-m-d H:i:s'));
        $request = $request->withHeader('content-type', 'text/html');

        $data = '{"key":"https://o3o.ca/users/yue#main-key","signature":"AqhTapM1FiwZIjg8CqXSK2Xe67xP7BYt/89CQGTuFL/VZ1UGXWrbmvlHPv3iyW1g04oZIerQn2iWlYMPUR/kmwEApL5TrDoEYDmEFsXDI5lSC0TOgPuqrfRJNng6OLRgKwuEJTHUirqt1enoS+Dn1IKWHmSMAH9+emSjzBYZDHUQfhFPiBvoqhhLn2zVY0gR2qiCfne6VJYrN51gTrbzwmnMOQzty70PPM1C2hfSMfl5N5xL0U0ZZBvhiUzjdixoYvvrO+GndBYQThGGa4EFK3Z+TrtX90QUAb1Mj+eZlQQMQN15HdSgiKj/KhabXqSO1Q6lcEQCyYkLhUjDjDAagQ","algorithm":"rsa-sha256","data":"(request-target): post /inbox\nhost: chokecherry.cc\ndate: Sat, 20 Feb 2021 08:08:14 GMT\ndigest: SHA-256=a17hcVru6fQq1lXgLuROvJQdQfSVO2Xfcm5GdWiKzRA=\ncontent-type: application/activity+json"}';
        $data = json_decode($data, true);
        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', '*', ['id' => 2]);


        $settings = $db->get('settings', '*', ['id' => 1]);
        $helper->withKey($settings['public_key'], $settings['private_key']);
        $res = $helper->verifyHttpSignature($data['data'], $data['signature']);

        $data =  json_encode($helper->getHttpSignatureDataFromRequest($request), JSON_UNESCAPED_SLASHES);
        $response->getBody()->write($data);

        return $response;
    }

    public function followers(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $db = $this->container->get(Medoo::class);
        $followers = $db->select('followers', [
            '[>]profiles' => ['profile_id' => 'id'],
        ], [
            'followers.id',
            'followers.profile_id',
            'profiles.avatar',
            'profiles.url',
            'profiles.account',
            'profiles.name',
            'profiles.preferred_name',
        ],['status' => 1]);

        return $this->render($response, 'followers', ['followers' => $followers]);
    }

    public function deleteFollower(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $followerId = $args['id'];

        if ($followerId) {
            $db = $this->container->get(Medoo::class);
            $follower = $db->get('followers', '*', ['id' => $followerId]);
            $db->delete('followers', ['id' => $followerId]);
            $db->insert('tasks', [
                'task' => 'LocalUndoTask',
                'params' => json_encode(['activity_id' => $follower['accept_activity_id']], JSON_UNESCAPED_SLASHES),
                'priority' => 140,
            ]);
        }

        return $this->redirectBack($request, $response);
    }

    public function deleteFollowing(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $followingId = $args['id'];

        if ($followingId) {
            $db = $this->container->get(Medoo::class);
            $following = $db->get('following', '*', ['id' => $followingId]);
            $db->delete('following', ['id' => $followingId]);
            $db->insert('tasks', [
                'task' => 'LocalUndoTask',
                'params' => json_encode(['activity_id' => $following['follow_activity_id']], JSON_UNESCAPED_SLASHES),
                'priority' => 140,
            ]);
        }

        return $this->redirectBack($request, $response);
    }

    public function following(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $db = $this->container->get(Medoo::class);
        $following = $db->select('following', [
            '[>]profiles' => ['profile_id' => 'id'],
        ], [
            'following.id',
            'following.profile_id',
            'profiles.avatar',
            'profiles.url',
            'profiles.account',
            'profiles.name',
            'profiles.preferred_name',
        ]);

        $flash = $this->flash($request);
        $data = [
            'following' => $following,
            'errors' => $flash->get('error', []),
            'messages' => $flash->get('success', [])
        ];

        return $this->render($response, 'following', $data);
    }

    public function tags(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tag = $args['tag'];

        $db = $this->container->get(Medoo::class);
        $tags = $db->select('tags', '*', [
            'term' => $tag,
            'profile_id' => 1,
            'LIMIT' => 10,
            'ORDER' => ['id' => 'DESC']
        ]);

        $objectIds = [];
        foreach ($tags as $v){
            if (!isset($objectIds[$v['object_id']])) {
                $objectIds[] = $v['object_id'];
            }
        }

        $objects = [];
        if (!empty($objectIds)) {
            $objects = $db->select('objects', [
                '[>]profiles' => ['profile_id' => 'id'],
            ], [
                'objects.id',
                'objects.raw_object_id',
                'objects.profile_id',
                'objects.type',
                'objects.content',
                'objects.summary',
                'objects.url',
                'objects.likes',
                'objects.replies',
                'objects.shares',
                'objects.published',
                'objects.is_local',
                'objects.is_public',
                'objects.is_boosted',
                'objects.is_sensitive',
                'profiles.actor',
                'profiles.name',
                'profiles.preferred_name',
                'profiles.url(profile_url)',
                'profiles.avatar',
                'profiles.account',
            ], [
                'objects.id' => $objectIds,
                'ORDER' => ['published' => 'DESC']
            ]);


            foreach ($objects as &$v) {
                $v['date'] = Time::getLocalTime($v['published']);
                if ($v['is_local']) {
                    preg_match('#\d{18}#', $v['raw_object_id'], $matches);
                    $v['snowflake_id'] =  $matches[0];
                }
            }
        }

        return $this->render($response, 'tag', [
            'notes' => $objects,
            'is_admin' => $this->isLogin($request)
        ]);
    }

    public function fetchProfile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $profileId = $args['profile_id'] ?? 0;
        if (!$profileId) {
            throw new HttpException($request, 'Profile id required', 400);
        }
        if ($profileId == 1) {
            throw new HttpException($request, 'Invalid profile id', 400);
        }
        $db = $this->container->get(Medoo::class);
        $actor = $db->get('profiles', 'actor', ['id' => $profileId]);
        if (empty($actor)) {
            throw new HttpException($request, 'Invalid profile id', 400);
        }
        $task = [
            'task' => 'FetchProfileTask',
            'params' => json_encode(['actor' => $actor], JSON_UNESCAPED_SLASHES),
            'priority' => 140,
        ];
        $db->insert('tasks', $task);
        return $this->redirectBack($request, $response);
    }

    public function showProfileForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $profileId = 1;
        $db = $this->container->get(Medoo::class);
        $profile = $db->get('profiles', ['id', 'name', 'avatar', 'summary'], ['id' => $profileId]);
        if (empty($profile)) {
            throw new HttpException($request, 'profile not found', 400);
        }
        $flash = $this->flash($request);
        $data = [
            'errors' => $flash->get('error', []),
            'messages' => $flash->get('success', []),
            'profile' => $profile
        ];
        return $this->render($response, 'settings/profile', $data);
    }

    public function updateProfile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->flash($request);
        $profileId = $args['profile_id'];
        if ($profileId != 1) {
            $flash->error('非法请求');
            return $this->redirectBack($request, $response);
        }

        $name = $this->getPostParam($request, 'name');
        $avatar = $this->getPostParam($request, 'avatar');
        $summary = $this->getPostParam($request, 'summary');
        if (empty($name) || empty($avatar)) {
            $flash->error('请完整填写相关资料');
            return $this->redirectBack($request, $response);
        }

        try {
            $db = $this->container->get(Medoo::class);
            $data = [
                'name' => $name,
                'avatar' => $avatar,
                'summary' => $summary
            ];
            $db->update('profiles', $data, ['id' => $profileId]);
            $task = [
                'task' => 'LocalUpdateProfileTask',
                'params' => json_encode(['id' => $profileId], JSON_UNESCAPED_SLASHES),
                'priority' => 140,
            ];
            $db->insert('tasks', $task);
            $flash->success('更新成功');
        } catch (Exception $e) {
            $flash->error($e->getMessage());
        }

        return $this->redirectBack($request, $response);
    }

    public function showInitialForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->flash($request);
        $data = [
            'errors' => $flash->get('error', []),
            'messages' => $flash->get('success', [])
        ];
        return $this->render($response, 'init', $data);
    }

    public function init(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $domain = $this->getPostParam($request, 'domain');
        $name = $this->getPostParam($request, 'name');
        $preferredName = $this->getPostParam($request, 'preferred_name');
        $password = $this->getPostParam($request, 'password');
        $confirmPassword = $this->getPostParam($request, 'confirm_password');
        $avatar = $this->getPostParam($request, 'avatar', '');
        $summary = $this->getPostParam($request, 'summary', '');

        try {
            if (empty($domain) || filter_var($domain, FILTER_VALIDATE_DOMAIN) === false) {
                throw new Exception('无效域名');
            }
            if (empty($name)) {
                throw new Exception('请填写昵称');
            }
            if (empty($preferredName)) {
                throw new Exception('请填写用户名');
            }
            if (empty($password) || empty($confirmPassword) || $password !== $confirmPassword) {
                throw new Exception('密码有误，请重新输入');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $config = array(
                "digest_alg" => "sha512",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            );
            $res = openssl_pkey_new($config);
            openssl_pkey_export($res, $privateKey );
            $publicKey =openssl_pkey_get_details($res);
            $publicKey = $publicKey["key"];
            $db = $this->container->get(Medoo::class);
            $db->insert('settings', [
                'id' => 1,
                'domain' => $domain,
                'password' => $hash,
                'public_key' => $publicKey,
                'private_key' => $privateKey,
            ]);

            $profile = [
                'id' => 1,
                'actor' => "https://$domain",
                'name' => $name,
                'preferred_name' => $preferredName,
                'account' => "$preferredName@$domain",
                'url' => "https://$domain",
                'avatar' => $avatar,
                'summary' => $summary,
                'inbox' => "https://$domain/inbox",
                'outbox' => "https://$domain/outbox",
                'following' => "https://$domain/following",
                'followers' => "https://$domain/followers",
                'featured' => "https://$domain/featured",
                'shared_inbox' => "https://$domain/inbox",
                'public_key' => $publicKey,
            ];
            $db->insert('profiles', $profile);

            return $response->withStatus('302')->withHeader('location', '/');
        } catch (Exception $e) {
            $flash = $this->flash($request);
            $flash->error($e->getMessage());
            return $this->redirectBack($request, $response);
        }
    }

    protected function render(ResponseInterface $response, string $template, array $data = [])
    {
        $engine = $this->container->get(Engine::class);
        $view = $engine->render($template, $data);
        $response->getBody()->write($view);
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

    protected function isLogin(ServerRequestInterface $request)
    {
        $session = $this->session($request);
        return $session->isStarted() && $session['is_admin'];
    }

    protected function getSettings()
    {
        $db = $this->container->get(Medoo::class);
        return $db->get('settings', '*', ['id' => 1]);
    }

    protected function redirectBack(ServerRequestInterface $request, ResponseInterface $response)
    {
        $referer = $request->getHeaderLine('Referer');
        $redirect = empty($referer) ? '/' : $referer;
        return $response->withStatus('302')->withHeader('location', $redirect);
    }

    protected function session(ServerRequestInterface $request): SessionInterface
    {
        if ($this->container->has('session')) {
            $session = $this->container->get('session');
        } else {
            $factory = $this->container->get(SessionInterface::class);
            $session = $factory($this->container, $request);
        }
        if (!$session->isStarted()) {
            $session->start();
        }
        return $session;
    }

    protected function flash(ServerRequestInterface $request): FlashMessage
    {
        $session = $this->session($request);
        return new FlashMessage($session);
    }

    protected function stripTags(string $html)
    {
        //@Todo remove invalid links in html
        return strip_tags($html, ['a', 'p', 'br', 'img', 'blockquote']);
    }
}