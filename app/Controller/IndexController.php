<?php
namespace Cherry\Controller;

use Cherry\ActivityPub\Activity;
use Cherry\ActivityPub\ActivityPub;
use Cherry\FlashMessage;
use Cherry\Helper\Helper;
use Cherry\Helper\Time;
use Cherry\Markdown;
use Cherry\Session\SessionInterface;
use Cherry\Task\{
    AcceptFollowTask,
    Cron\DeleteExpiredSessionsTask,
    Cron\UpdateRemotePollsTask,
    DeleteActivityTask,
    DeliverActivityTask,
    FetchProfileTask,
    FollowTask,
    LocalInteractiveTask,
    LocalUndoTask,
    LocalUpdateProfileTask,
    LocalVoteTask,
    RejectFollowTask,
    TaskQueue
};
use Godruoyi\Snowflake\Snowflake;
use League\Plates\Engine;
use Medoo\Medoo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\RouteCollectorInterface;
use GuzzleHttp\Cookie\SetCookie;
use Exception;
use InvalidArgumentException;
use DirectoryIterator;

class IndexController extends BaseController
{
    public function home(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (Helper::isApi($request)) {
            /** @var RouteCollectorInterface $router */
            $router = $this->container->get('router');
            return $router->getNamedRoute('api_profile')->run($request);
        }

        $db = $this->db();
        $profile = $this->adminProfile();

        $pageSize = 10;
        $index = $this->getQueryParam($request, 'index', null);

        $defaultConditions = [
            'activities.object_id[!]' => 0,
            'activities.type' => ['Create', 'Announce'],
            'activities.unlisted' => 0,
            'activities.is_local' => 1,
            'activities.is_deleted' => 0,
            'LIMIT' => $pageSize,
            'ORDER' => ['activities.id' => 'DESC'],
        ];
        if (!$this->isLoggedIn($request)) {
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
            'objects.published',
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
        $objectAttachments = $this->getAttachmentMapByObjectIds($objectIds);

        foreach ($blogs as &$v) {
            if (empty($v['profile_id'])) {
                continue;
            }
            $v['relative_time'] = Time::relativeUnit($v['published'], 'short_', 'hour');
            $v['date'] = Time::getLocalTime($v['published'], 'Y-m-d');
            $objectInfo = $objectProfiles[$v['profile_id']];
            $v['profile_id'] = $objectInfo['id'];
            $v['actor'] = $objectInfo['actor'];
            $v['preferred_name'] = $objectInfo['preferred_name'];
            $v['name'] = $objectInfo['name'];
            $v['content'] = Helper::stripTags($v['content']);
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
            'unlisted' => 0,
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

        return $this->render($response, 'index', [
            'profile' => $profile,
            'blogs' => $blogs,
            'counts' => $counts,
            'is_admin' => $this->isLoggedIn($request),
            'notifications' => $notificationsCount,
            'prev' => $prevIndex,
            'next' => $nextIndex,
        ]);
    }

    public function timeline(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $index = $this->getQueryParam($request, 'index');
        $pid = $this->getQueryParam($request, 'pid');

        $keys = ['group_activities'];
        $settings = $this->container->make('settings', ['keys' => $keys]);
        $groupActivities = isset($settings['group_activities']) ? (int)$settings['group_activities'] : 0;

        $db = $this->db();

        $activityIds = $this->getActivityIdsForTimelineV2($request, $groupActivities, $index, $pid);
        $blogs = [];
        if (!empty($activityIds['current'])) {
            $blogs = $db->select('activities', [
                '[>]profiles' => ['profile_id' => 'id'],
                '[>]objects' => ['object_id' => 'id'],
            ], [
                'activities.id',
                'activities.object_id',
                'activities.activity_id',
                'activities.profile_id(activity_profile_id)',
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
                'objects.published',
            ], [
                'activities.id' => $activityIds['current'],
                'ORDER' => ['activities.id' => 'DESC']
            ]);
        }

        $objectProfileIds = [];
        $objectIds = [];
        $parentIds = [];
        $pollIds = [];
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
            if ($v['type'] === ActivityPub::OBJECT_TYPE_QUESTION) {
                $pollIds[] = $v['object_id'];
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

        $objectAttachments = $this->getAttachmentMapByObjectIds($objectIds);
        $pollMap = $this->getPollMapByObjectIds($pollIds);
        foreach ($blogs as &$v) {
            if (empty($v['profile_id'])) {
                continue;
            }
            $v['relative_time'] = Time::relativeUnit($v['published'], 'short_', 'hour');
            $v['date'] = Time::getLocalTime($v['published'], 'Y-m-d');
            $objectInfo = $objectProfiles[$v['profile_id']];
            $v['profile_id'] = $objectInfo['id'];
            $v['actor'] = $objectInfo['actor'];
            $v['preferred_name'] = $objectInfo['preferred_name'];
            $v['name'] = $objectInfo['name'];
            $v['content'] = Helper::stripTags($v['content']);
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
            if (isset($pollMap[$v['object_id']])) {
                $v['poll'] = $pollMap[$v['object_id']];
            }
            unset($v);
        }

        if (!empty($blogs)) {
            $prevIndex = empty($activityIds['prev']) ? 0 : $activityIds['prev'][0];
            $nextIndex = empty($activityIds['next']) ? 0 : $activityIds['next'][0];
            $prevArgs = $prevIndex ? ['index' => $prevIndex] : [];
            $nextArgs = $nextIndex ? ['index' => $nextIndex] : [];
            if ($pid) {
                $prevArgs['pid'] = $pid;
                $nextArgs['pid'] = $pid;
            }
        }

        return $this->render($response, 'timeline', [
            'blogs' => $blogs,
            'prev' => empty($prevArgs['index']) ? null : http_build_query($prevArgs),
            'next' => empty($nextArgs['index']) ? null : http_build_query($nextArgs),
            'is_admin' => $this->isLoggedIn($request),
        ]);
    }

    protected function getPollMapByObjectIds(array $objectIds): array
    {
        $db = $this->db();
        $pollMap = [];
        if (!empty($objectIds)) {
            $polls = $db->select('polls', '*', ['object_id' => $objectIds]);
            $ids = [];
            foreach ($polls as $p) {
                $ids[] = $p['id'];
            }
            if (!empty($ids)) {
                $myChoices = $db->select('poll_choices', '*', ['poll_id' => $ids, 'profile_id' => 1]);
                foreach ($polls as &$pd) {
                    $pd['is_closed'] = $pd['is_closed'] || strtotime($pd['end_time']) < time();
                    if (!$pd['is_closed']) {
                        $relativeTime = Time::relativeUnit($pd['end_time'], 'verbose_', 'day');
                        $pd['time_left'] = $relativeTime['time'];
                        $pd['time_left_type'] = $relativeTime['unit'];
                    }
                    $pd['choices'] = json_decode($pd['choices'], true);
                    foreach ($pd['choices'] as &$pc) {
                        $pc['selected'] = false;
                        $pc['percent'] = ($pd['voters_count'] > 0 ? $pc['count'] / $pd['voters_count'] : 0) * 100;
                        foreach ($myChoices as $c) {
                            if ($c['poll_id'] !== $pd['id']) {
                                continue;
                            }
                            if ($pc['name'] === $c['choice']) {
                                $pc['selected'] = true;
                            }
                        }
                    }
                    $pollMap[$pd['object_id']] = $pd;
                }
            }
        }

        return $pollMap;
    }

    protected function getAttachmentMapByObjectIds(array $objectIds): array
    {
        $objectAttachments = [];
        if (!empty($objectIds)) {
            $attachments = $this->db()->select('attachments', '*', ['object_id' => $objectIds]);
            foreach ($attachments as $v) {
                if (!isset($objectAttachments[$v['object_id']])) {
                    $objectAttachments[$v['object_id']] = [];
                }
                $objectAttachments[$v['object_id']][] = $v;
            }
        }
        return $objectAttachments;
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($this->isLoggedIn($request)) {
            return $response->withStatus('302')->withHeader('location', '/timeline');
        }
        $profile = $this->adminProfile();
        $flash = $this->flash($request);
        $data = [
            'profile' => $profile,
            'errors' => $flash->get('error', []),
        ];
        return $this->render($response, 'login', $data);
    }

    public function verifyPassword(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $password = $this->getPostParam($request, 'password');
            if (!$password) {
                throw new Exception('Password required');
            }
            $keys = ['password', 'login_retry', 'deny_login_until'];
            $settings = $this->container->make('settings', ['keys' => $keys]);
            $now = time();
            if ($now < $settings['deny_login_until']) {
                throw new Exception('Login Denied');
            }
            $maxRetry = 3;
            $denySeconds = 60 * 60 * 2;
            if (!password_verify($password, $settings['password'])) {
                $db = $this->db();
                $currentRetry = $settings['login_retry'] + 1;
                if ($currentRetry >= $maxRetry) {
                    $db->update('settings', ['v' => $now + $denySeconds], ['k' => 'deny_login_until', 'cat' => 'system']);
                    $db->update('settings', ['v' => 0], ['k' => 'login_retry', 'cat' => 'system']);
                    throw new Exception('Too many failed login attempts');
                } else {
                    $db->update('settings', ['v' => $currentRetry], ['k' => 'login_retry', 'cat' => 'system']);
                    throw new Exception('Invalid password');
                }
            }
            $session = $this->session($request);
            $session['is_admin'] = true;
            $redirect = '/editor';
        } catch (Exception $e) {
            $redirect = '/login';
            $flash = $this->flash($request);
            $flash->error($e->getMessage());
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
        $summary = $this->getPostParam($request, 'summary');
        $summary = empty($summary) ? null : $summary;
        $scope = $this->getPostParam($request, 'scope');

        // 回复嘟文的编号
        $inReplyTo = $this->getPostParam($request, 'in_reply_to');

        $db = $this->db();
        $profile = $db->get('profiles', '*', ['id' => 1]);
        $domain = parse_url($profile['actor'], PHP_URL_HOST);

        // 嘟文
        $markdown = $this->container->get(Markdown::class);
        $markdown->setTagHost($domain);
        $parsedContent = $markdown->text($content);

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
        $published = Time::UTCTimeISO8601();
        $object = [
            'id' => "{$profile['outbox']}/$objectId/object",
            'url' => "https://$domain/notes/$objectId",
            'type' => 'Note',
            'attributedTo' => $profile['actor'],
            'summary' => $summary,
            'content' => $parsedContent,
            'sensitive' => is_null($summary) ? false : true,
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

        $objectTags = [];

        $actorMentions = $markdown->getMentions();
        if (!empty($actorMentions)) {
            if (!isset($audiences['cc'])) {
                $audiences['cc'] = [];
            }
            foreach ($actorMentions as $mention) {
                $objectTags[] = [
                    'type' => 'Mention',
                    'href' => $mention['actor'],
                    'name' => '@' . $mention['account']
                ];
                if (!in_array($mention['actor'], $audiences['cc'])) {
                    $audiences['cc'][] = $mention['actor'];
                }
            }
        }

        if (!empty($replyProfile) && !empty($replyObject)) {
            $objectTags[] = [
                'type' => 'Mention',
                'href' => $replyProfile['actor'],
                'name' => '@' . $replyProfile['account'],
            ];
            $object = array_merge($object, ['inReplyTo' => $replyObject['raw_object_id']]);
        }

        $object = array_merge($object, $audiences);

        // 新 Activity 信息
        $activity = Activity::createFromArray(array_merge([
            'id' => "https://$domain/outbox/$objectId",
            'type' => 'Create',
            'actor' => $profile['actor'],
            'published' => $published,
        ], $audiences));

        try {
            $db->pdo->beginTransaction();

            $parentId = !empty($replyObject['id']) ? $replyObject['id'] : 0;
            $originId = !empty($replyObject['origin_id']) ? $replyObject['origin_id'] : $parentId;
            // 保存新 Object
            $db->insert('objects', [
                'type' => $object['type'],
                'profile_id' => 1,
                'raw_object_id' => $object['id'],
                'content' => $object['content'],
                'summary' => $object['summary'] ?? '',
                'url' => $object['url'],
                'published' => Time::UTCToLocalTime($object['published']),
                'is_local' => 1,
                'is_public' => $scope < 3 ? 1 : 0,
                'origin_id' => $originId,
                'parent_id' => $parentId,
                'is_sensitive' => is_null($summary) ? 0 : 1,
            ]);
            $objectId = $db->id();

            $tags = $markdown->hashTags();
            if (!empty($tags)) {
                $terms = [];
                foreach ($tags as $v) {
                    $terms[] = [
                        'term' => $v,
                        'profile_id' => 1,
                        'object_id' => $objectId,
                    ];
                    $objectTags[] = [
                        'type' => 'Hashtag',
                        'name' => "#$v",
                        'href' => "{$profile['actor']}/tags/$v",
                    ];
                }
                $db->insert('tags', $terms);
            }

            // 添加 tag 信息
            if (!empty($objectTags)) {
                $object['tag'] = $objectTags;
            }

            $activity->set('object', $object);

            // 保存新 Activity
            $db->insert('activities', [
                'activity_id' => $activity->id,
                'profile_id' => 1,
                'object_id' => $objectId,
                'type' => 'Create',
                'raw' => json_encode($activity->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
                    'published' => $activity->published,
                ]);
            }

            // 添加推送任务
            if (!empty($audiences['to']) || !empty($audiences['cc'])) {
                $this->container->get(TaskQueue::class)->queue([
                    'task' => DeliverActivityTask::class,
                    'params' => ['activity_id' => $activityId]
                ]);
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
        $db = $this->db();
        $profile = $db->get('profiles', ['id', 'outbox'], ['id' => 1]);
        $outboxId = sprintf('%s/%s', $profile['outbox'], $snowflakeId);
        $activity = $db->get('activities', ['id', 'object_id'], ['activity_id' => $outboxId]);

        // 标记为已删除
        $db->update('activities', ['is_deleted' => 1], [
             'OR' => [
                 'id' => $activity['id'],
                 'object_id' => $activity['object_id'],
             ]
        ]);
        // 删除嘟文
        $db->delete('objects', ['id' => $activity['object_id']]);
        // 删除互动数据
        $db->delete('interactions', ['object_id' => $activity['object_id']]);
        // 删除标签
        $db->delete('tags', ['object_id' => $activity['object_id']]);

        $this->container->get(TaskQueue::class)->queue([
            'task' => DeleteActivityTask::class,
            'params' => ['activity_id' => $activity['id']]
        ]);

        return $response->withStatus('302')->withHeader('location', '/');
    }

    public function note(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $snowflakeId = $args['snowflake_id'];
        $db = $this->db();
        $profile = $db->get('profiles', ['id', 'outbox'], ['id' => 1]);
        $outboxId = sprintf('%s/%s', $profile['outbox'], $snowflakeId);
        $activity = $db->get('activities', ['id', 'object_id'], [
            'activity_id' => $outboxId,
            'is_public' => 1,
            'is_deleted' => 0,
        ]);

        if (empty($activity)) {
            throw new HttpNotFoundException($request);
        }

        $notes = $db->select('objects', [
            '[>]profiles' => ['profile_id' => 'id'],
        ], [
            'objects.id',
            'objects.id(object_id)',
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
            'OR' => [
                'objects.id' => $activity['object_id'],
                'objects.parent_id' => $activity['object_id'],
                'objects.origin_id' => $activity['object_id'],
            ],
            'objects.unlisted' => 0,
            'objects.is_public' => 1,
            'ORDER' => ['published' => 'ASC']
        ]);

        $objectIds = [];
        foreach ($notes as $v) {
            $objectIds[] = $v['id'];
        }
        $objectAttachments = $this->getAttachmentMapByObjectIds($objectIds);

        $note = null;
        $replies = [];
        foreach ($notes as $v) {
            $v['date'] = Time::getLocalTime($v['published'], 'Y-m-d');
            if ($v['is_local']) {
                preg_match('#\d{18}#', $v['raw_object_id'], $matches);
                $v['snowflake_id'] =  $matches[0];
            }
            $v['attachments'] = $objectAttachments[$v['id']] ?? [];
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

        array_unshift($replies, $note);
        return $this->render($response, 'note', [
            'notes' => $replies,
            'note_id' => $note['id'],
            'interactions' => $interactions,
            'is_admin' => $this->isLoggedIn($request),
        ]);
    }

    public function replyTo(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $objectId = $args['object_id'];
        $db = $this->db();
        $profile = $this->adminProfile();
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
        $object['object_id'] = $object['id'];
        $object['date'] = Time::getLocalTime($object['published'], 'Y-m-d');
        if ($object['is_local']) {
            preg_match('#\d{18}#', $object['raw_object_id'], $matches);
            $object['snowflake_id'] =  $matches[0];
        }
        $object['show_boosted'] = false;

        $at = "@{$object['account']} ";

        $attachments = $this->getAttachmentMapByObjectIds([$objectId]);
        $object['attachments'] = $attachments[$objectId] ?? [];
        $polls = $this->getPollMapByObjectIds([$objectId]);
        $object['poll'] = $polls[$objectId] ?? [];

        return $this->render($response, 'editor', [
            'note' => $object,
            'profile' => $profile,
            'at' => $at,
            'is_admin' => $this->isLoggedIn($request),
        ]);
    }

    public function showThread(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $objectId = $args['object_id'];
        $db = $this->db();
        $object = $db->get('objects', ['origin_id', 'parent_id'], ['id' => $objectId]);
        if (empty($object)) {
            throw new HttpNotFoundException($request, 'Object Not Found');
        }
        $rootObjectId = $object['origin_id'] ?: $objectId;
        $notes = $db->select('objects', [
            '[>]profiles' => ['profile_id' => 'id'],
        ], [
            'objects.id',
            'objects.id(object_id)',
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
            'OR' => [
                'objects.id' => $rootObjectId,
                'objects.parent_id' => $rootObjectId,
                'objects.origin_id' => $rootObjectId,
            ],
            'objects.unlisted' => 0,
            'ORDER' => ['published' => 'ASC']
        ]);

        $objectIds = [];
        foreach ($notes as $v) {
            $objectIds[] = $v['id'];
        }
        $objectAttachments = $this->getAttachmentMapByObjectIds($objectIds);

        foreach ($notes as &$v) {
            $v['date'] = Time::getLocalTime($v['published'], 'Y-m-d');
            if ($v['is_local']) {
                preg_match('#\d{18}#', $v['raw_object_id'], $matches);
                $v['snowflake_id'] =  $matches[0];
            }
            $v['attachments'] = $objectAttachments[$v['id']] ?? [];
            $v['content'] = Helper::stripTags($v['content']);
        }

        return $this->render($response, 'note', [
            'notes' => $notes,
            'note_id' => -1,
            'interactions' => [],
            'is_admin' => $this->isLoggedIn($request),
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
            $isUrl = true;
            if (filter_var($account, FILTER_VALIDATE_URL) === false) {
                if (strpos($account, '@') === 0) {
                    $account = substr($account, 1);
                }
                $accountArr = explode('@', $account);
                if (count($accountArr) !== 2) {
                    throw new InvalidArgumentException('Invalid Account');
                }
                $isUrl = false;
            }
            $this->container->get(TaskQueue::class)->queue([
                'task' => FollowTask::class,
                'params' => ['account' => $account, 'is_url' => $isUrl]
            ]);
            $this->flash($request)->success('Follow Request Sent!');
        } catch (Exception $e) {
            $this->flash($request)->error($e->getMessage());
        }
        return $response->withStatus('302')->withHeader('location', '/web/following');
    }

    public function notifications(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $db = $this->db();
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
            'a.object_id',
            'a.published',
        ], [
            'LIMIT' => 10,
            'ORDER' => ['id' => 'DESC']
        ]);
        $notificationIds = [];
        foreach ($notifications as &$v) {
            $notificationIds[] = $v['id'];
            if (empty($v['raw'])) {
                continue;
            }
            $v['raw'] = json_decode($v['raw'], true);
            $v['raw_object_id'] = Activity::rawObjectId($v['raw']);
            $v['published'] = Time::getLocalTime($v['published'], 'Y-m-d');
        }
        $db->update('notifications', ['viewed' => 1], ['id' => $notificationIds]);

        return $this->render($response, 'notifications', ['notifications' => $notifications]);
    }

    public function handleFollowRequest(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $action = $this->getQueryParam($request, 'action');
            $notificationId = $args['notification_id'];
            if (empty($action) || empty($notificationId)) {
                throw new InvalidArgumentException('Both action and notification id required');
            }
            $db = $this->db();
            $notification = $db->get('notifications', '*', ['id' => $notificationId]);

            $params = [
                'activity_id' => $notification['activity_id'],
                'profile_id' => $notification['profile_id'],
            ];

            $taskQueue = $this->container->get(TaskQueue::class);
            if ($action === 'accept') {
                $taskQueue->queue([
                    'task' => AcceptFollowTask::class,
                    'params' => $params
                ]);
                $db->update('notifications', ['status' => 1, 'viewed' => 1], ['id' => $notificationId]);
            } else {
                if ($action === 'reject') {
                    $taskQueue->queue([
                        'task' => RejectFollowTask::class,
                        'params' => $params
                    ]);
                }
                $db->delete('notifications', ['id' => $notificationId]);
            }
        } catch (Exception $e) {
            // pass
        }
        return $response->withStatus('302')->withHeader('location', '/notifications');
    }

    public function liked(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $objectId = $args['object_id'];
        $db = $this->db();
        $object = $db->get('objects', ['id', 'is_liked'], ['id' => $objectId]);
        $liked = $object['is_liked'] ? 0 : 1;
        $db->update('objects', ['is_liked' => $liked], ['id' => $objectId]);
        $taskQueue = $this->container->get(TaskQueue::class);
        if ($liked) {
            $taskQueue->queue([
                'task' => LocalInteractiveTask::class,
                'params' => ['object_id' => $objectId, 'type' => 'Like']
            ]);
        } else if ($object['is_liked']) {
            $interaction = $db->get('interactions', ['activity_id'], ['profile_id' => 1, 'object_id' => $objectId]);
            $taskQueue->queue([
                'task' => LocalUndoTask::class,
                'params' => [ 'activity_id' => $interaction['activity_id']]
            ]);
        }
        $referer = $request->getHeaderLine('Referer');
        $redirect = empty($referer) ? '/' : $referer;
        return $response->withStatus('302')->withHeader('location', $redirect);
    }

    public function boosted(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $objectId = $args['object_id'];
        $db = $this->db();
        $object = $db->get('objects', ['id', 'is_boosted', 'is_public'], ['id' => $objectId]);
        if (!$object['is_public']) {
            // 检查是否运行转载
            goto REDIRECT_BACK;
        }

        $toBoost = $object['is_boosted'] ? 0 : 1;
        $db->update('objects', ['is_boosted' => $toBoost], ['id' => $objectId]);
        $taskQueue = $this->container->get(TaskQueue::class);
        if ($toBoost) {
            $taskQueue->queue([
                'task' => LocalInteractiveTask::class,
                'params' => ['object_id' => $objectId, 'type' => 'Announce']
            ]);
        } else if ($object['is_boosted']) {
            $interaction = $db->get('interactions', ['activity_id'], ['profile_id' => 1, 'object_id' => $objectId]);
            $taskQueue->queue([
                'task' => LocalUndoTask::class,
                'params' => ['activity_id' => $interaction['activity_id']]
            ]);
        }

        REDIRECT_BACK:
        $referer = $request->getHeaderLine('Referer');
        $redirect = empty($referer) ? '/' : $referer;
        return $response->withStatus('302')->withHeader('location', $redirect);
    }

    public function vote(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $pollId = $args['poll_id'];
        $choices = $this->getPostParam($request, 'choice', []);
        $db = $this->db();
        $poll = $db->get('polls', '*', ['id' => $pollId]);
        if (!$poll['multiple']) {
            $choices = [$choices];
        }
        $choiceData = json_decode($poll['choices'], true);
        foreach ($choices as $v) {
            $choice = [
                'poll_id' => $pollId,
                'profile_id' => 1,
                'choice' => $v,
                'vote_time' => Time::getLocalTime(),
            ];
            $db->insert('poll_choices', $choice);
            $choiceId = $db->id();
            $this->container->get(TaskQueue::class)->queue([
                'task' => LocalVoteTask::class,
                'params' => ['choice_id' => $choiceId]
            ]);
            foreach ($choiceData as &$c) {
                if ($c['name'] === $v) {
                    $c['count'] += 1;
                    break;
                }
            }
        }
        $jsonChoiceData = json_encode($choiceData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $db->update('polls', [
            'is_voted' => 1,
            'choices' => $jsonChoiceData,
            'voters_count[+]' => 1
        ], ['id' => $pollId]);
        return $this->redirectBack($request, $response);
    }

    public function followers(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $page = $this->getQueryParam($request, 'page', 1);
        $size = 10;
        $offset = ($page - 1) * $size;
        $db = $this->db();
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
        ],[
            'status' => 1,
            'LIMIT' => [$offset, $size]
        ]);
        $total = $db->count('followers', ['status' => 1]);
        $prev = $page > 1 ? $page - 1 : 0;
        $next = $page * $size < $total ? $page + 1 : 0;

        return $this->render($response, 'followers', [
            'followers' => $followers,
            'prev' => $prev,
            'next' => $next,
        ]);
    }

    public function deleteFollower(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $followerId = $args['id'];

        if ($followerId) {
            $db = $this->db();
            $follower = $db->get('followers', '*', ['id' => $followerId]);
            $db->delete('followers', ['id' => $followerId]);
            $this->container->get(TaskQueue::class)->queue([
                'task' => LocalUndoTask::class,
                'params' => ['activity_id' => $follower['accept_activity_id']]
            ]);
        }

        return $this->redirectBack($request, $response);
    }

    public function deleteFollowing(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $followingId = $args['id'];

        if ($followingId) {
            $db = $this->db();
            $following = $db->get('following', '*', ['id' => $followingId]);
            $db->delete('following', ['id' => $followingId]);
            $this->container->get(TaskQueue::class)->queue([
                'task' => LocalUndoTask::class,
                'params' => ['activity_id' => $following['follow_activity_id']]
            ]);
        }

        return $this->redirectBack($request, $response);
    }

    public function following(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $page = $this->getQueryParam($request, 'page', 1);
        $size = 10;
        $offset = ($page - 1) * $size;
        $db = $this->db();
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
        ], [
            'LIMIT' => [$offset, $size]
        ]);
        $total = $db->count('following');
        $prev = $page > 1 ? $page - 1 : 0;
        $next = $page * $size < $total ? $page + 1 : 0;

        $flash = $this->flash($request);
        $data = [
            'following' => $following,
            'errors' => $flash->get('error', []),
            'messages' => $flash->get('success', []),
            'prev' => $prev,
            'next' => $next,
        ];

        return $this->render($response, 'following', $data);
    }

    public function tags(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tag = $args['tag'];
        $db = $this->db();
        $conditions = [
            'term' => $tag,
            'LIMIT' => 10,
            'ORDER' => ['id' => 'DESC']
        ];
        if (!$this->isLoggedIn($request)) {
            $conditions['profile_id'] = CHERRY_ADMIN_PROFILE_ID;
        }
        $tags = $db->select('tags', ['object_id'], $conditions);

        $objectIds = [];
        foreach ($tags as $v){
            if (!isset($objectIds[$v['object_id']])) {
                $objectIds[] = $v['object_id'];
            }
        }

        $activities = [];
        if (!empty($objectIds)) {
            $selectConditions = [
                'activities.object_id' => $objectIds,
                'activities.type' => ['Create', 'Announce'],
                'activities.is_public' => 1,
                'activities.is_deleted' => 0,
                'ORDER' => ['published' => 'DESC']
            ];
            // show all activities after logging in
            if ($this->isLoggedIn($request)) {
                unset($selectConditions['activities.is_public']);
            }
            $activities = $db->select('activities', [
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
            ], $selectConditions);

            $objectProfileIds = [];
            $objectIds = [];
            $parentIds = [];
            foreach ($activities as $v) {
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
            $objectAttachments = $this->getAttachmentMapByObjectIds($objectIds);

            foreach ($activities as &$v) {
                if (empty($v['profile_id'])) {
                    continue;
                }
                $v['date'] = Time::getLocalTime($v['published'], 'Y-m-d');
                $objectInfo = $objectProfiles[$v['profile_id']];
                $v['profile_id'] = $objectInfo['id'];
                $v['actor'] = $objectInfo['actor'];
                $v['preferred_name'] = $objectInfo['preferred_name'];
                $v['name'] = $objectInfo['name'];
                $v['content'] = Helper::stripTags($v['content']);
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
        }

        return $this->render($response, 'tag', [
            'notes' => $activities,
            'is_admin' => $this->isLoggedIn($request)
        ]);
    }

    public function fetchProfile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $profileId = $args['profile_id'] ?? 0;
        if (!$profileId) {
            throw new HttpException($request, 'Profile id required', 400);
        }
        if ($profileId == CHERRY_ADMIN_PROFILE_ID) {
            throw new HttpException($request, 'Invalid profile id', 400);
        }
        $actor = $this->db()->get('profiles', 'actor', ['id' => $profileId]);
        if (empty($actor)) {
            throw new HttpException($request, 'Invalid profile id', 400);
        }
        $this->container->get(TaskQueue::class)->queue([
            'task' => FetchProfileTask::class,
            'params' => ['actor' => $actor]
        ]);
        return $this->redirectBack($request, $response);
    }

    public function showProfileForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $profile = $this->adminProfile();
        if (empty($profile)) {
            throw new HttpException($request, 'profile not found', 400);
        }

        $settings = $this->getSettings();
        $themes = ['default'];
        $dir = new DirectoryIterator(ROOT . '/public/themes');
        foreach ($dir as $file) {
            if ($file->isDot()) {
                continue;
            }
            if ($file->isFile() && $file->getExtension() === 'css') {
                $themes[] = $file->getBasename('.css');
            }
        }
        $languages = [];
        $dir = new DirectoryIterator(ROOT . '/app/lang');
        foreach ($dir as $file) {
            if ($file->isDot()) {
                continue;
            }
            if ($file->isDir()) {
                $languages[] = $file->getFilename();
            }
        }
        $flash = $this->flash($request);
        $data = [
            'errors' => $flash->get('error', []),
            'messages' => $flash->get('success', []),
            'profile' => $profile,
            'settings' => $settings,
            'themes' => $themes,
            'languages' => $languages,
        ];
        return $this->render($response, 'settings/profile', $data);
    }

    public function updateProfile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->flash($request);
        $profileId = $args['profile_id'];
        if ($profileId != CHERRY_ADMIN_PROFILE_ID) {
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
            $data = [
                'name' => $name,
                'avatar' => $avatar,
                'summary' => $summary
            ];
            $this->db()->update('profiles', $data, ['id' => $profileId]);
            $this->container->get(TaskQueue::class)->queue([
                'task' => LocalUpdateProfileTask::class,
                'params' => ['id' => $profileId]
            ]);
            $flash->success('更新成功');
        } catch (Exception $e) {
            $flash->error($e->getMessage());
        }

        return $this->redirectBack($request, $response);
    }

    public function updatePreferences(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $keys = [
            'lock_site' => 0,
            'theme' => 'default',
            'language' => 'en',
            'group_activities' => 0,
        ];
        $preferences = [];
        foreach ($keys as $k => $v) {
            $preferences[$k] = $this->getPostParam($request, $k, $v);
        }
        $settings = $this->container->make('settings', ['keys' => array_keys($keys)]);
        $updated = [];
        $updatedKeys = [];
        $cases = '';
        foreach ($settings as $k => $v) {
            if (isset($preferences[$k]) && $preferences[$k] !== $v) {
                $updated[':' . $k] = $preferences[$k];
                $updatedKeys[] = $k;
                $cases .= sprintf("WHEN `k` = '%s' THEN :%s ", $k, $k);
            }
        }
        $flash = $this->flash($request);
        if (!empty($updated)) {
            $kStrings = '';
            foreach ($updatedKeys as $key) {
                $kStrings .= "'{$key}',";
            }
            $kStrings = rtrim($kStrings, ',');
            $sql = <<<SQL
UPDATE `settings` SET `v` = CASE
    %s
    END
WHERE `cat` = 'system' AND `k` IN (%s);
SQL;
            $sql = sprintf($sql, $cases, $kStrings);
            $statement = $this->db()->pdo->prepare($sql);
            $statement->execute($updated);
            $flash->success('Updated successfully');
        } else {
            $flash->success('Nothing changed');
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
            $db = $this->db();
            $db->insert('settings', [
                ['cat' => 'system', 'k' => 'domain', 'v' => $domain],
                ['cat' => 'system', 'k' => 'password', 'v' => $hash],
                ['cat' => 'system', 'k' => 'public_key', 'v' => $publicKey],
                ['cat' => 'system', 'k' => 'private_key', 'v' => $privateKey],
                ['cat' => 'system', 'k' => 'login_retry', 'v' => 0],
                ['cat' => 'system', 'k' => 'deny_login_until', 'v' => 0],
                ['cat' => 'system', 'k' => 'theme', 'v' => 'default'],
                ['cat' => 'system', 'k' => 'language', 'v' => 'en'],
                ['cat' => 'system', 'k' => 'lock_site', 'v' => 0],
                ['cat' => 'system', 'k' => 'group_activities', 'v' => 0],
            ]);

            $profile = [
                'id' => CHERRY_ADMIN_PROFILE_ID,
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

            $tasks = [
                [
                    'task' => DeleteExpiredSessionsTask::class,
                    'priority' => 120,
                    'is_loop' => 1,
                ],
                [
                    'task' => UpdateRemotePollsTask::class,
                    'priority' => 120,
                    'is_loop' => 1,
                ],
            ];
            $this->container->get(TaskQueue::class)->queueArray($tasks);
            return $response->withStatus('302')->withHeader('location', '/login');
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

    protected function isLoggedIn(ServerRequestInterface $request)
    {
        $session = $this->session($request);
        return $session->isStarted() && $session['is_admin'];
    }

    protected function getSettings()
    {
        return $this->container->make('settings');
    }

    protected function redirectBack(ServerRequestInterface $request, ResponseInterface $response)
    {
        $referer = $request->getHeaderLine('Referer');
        $redirect = empty($referer) ? '/' : $referer;
        return $response->withStatus('302')->withHeader('location', $redirect);
    }

    protected function session(ServerRequestInterface $request): SessionInterface
    {
        return $this->container->make(SessionInterface::class, ['request' => $request]);
    }

    protected function flash(ServerRequestInterface $request): FlashMessage
    {
        $session = $this->session($request);
        return new FlashMessage($session);
    }

    protected function hasSessionId(ServerRequestInterface $request, SessionInterface $session)
    {
        $cookies = $request->getCookieParams();
        foreach ($cookies as $k => $v) {
            if ($k === $session->getName()) {
                return true;
            }
        }
        return false;
    }

    protected function getActivityIdsForTimeline(string $type, int $groupActivities, int $index = null, int $pid = null, int $size = 10): array
    {
        $commonConditions = [
            'type' => ['Create', 'Announce'],
            'unlisted' => 0,
            'is_local' => 0,
            'is_deleted' => 0,
            'LIMIT' => $size,
            'ORDER' => ['id' => 'DESC'],
        ];
        $pidCondition = $pid ? ['profile_id' => $pid] : [];
        $commonConditions = array_merge($commonConditions, $pidCondition);
        $objectCondition = ['object_id[!]' => 0];
        $db = $this->db();

        if (($type === 'next' || $type === 'prev') && is_null($index)) {
            return [];
        }

        if ($type === 'next') {
            $conditions = array_merge($commonConditions, $objectCondition, [
                'id[<]' => $index,
                'LIMIT' => 1,
            ]);
            $activityIds = $db->select('activities', 'id', $conditions);
            return $activityIds;
        }

        if ($type === 'current') {
            $indexCondition = $index ? ['id[<=]' =>  $index] : [];
            $conditions = array_merge($commonConditions, $indexCondition);
            $selectedColumns = is_null($pid) && $groupActivities ? ['id' => Medoo::raw('max(id)')] : ['id'];
        } else {
            $conditions = array_merge($commonConditions, [
                'id[>]' => $index,
                'ORDER' => ['id' => 'ASC']
            ]);
            $selectedColumns = is_null($pid) && $groupActivities ? ['id' => Medoo::raw('min(id)')] : ['id'];
        }
        if (is_null($pid) && $groupActivities) {
            $distinctObjectConditions = array_merge($conditions, $objectCondition);
            $distinctObjectIds = $db->select('activities', '@object_id', $distinctObjectConditions);
            if (empty($distinctObjectIds)) {
                return  [];
            }
            $conditions = array_merge($conditions, ['object_id' => $distinctObjectIds], ['GROUP' => 'object_id']);
        } else {
            $conditions = array_merge($conditions, $objectCondition);
        }
        $activityIds = $db->select('activities', $selectedColumns, $conditions);
        $activityIds = array_map(function ($v) {
            return $v['id'];
        }, $activityIds);
        return $activityIds;
    }

    protected function getActivityIdsForTimelineV2(
        ServerRequestInterface $request,
        int $groupActivities,
        int $index = null,
        int $pid = null,
        int $size = 10
    ): array {
        $commonConditions = [
            'type' => ['Create', 'Announce'],
            'unlisted' => 0,
            'is_local' => 0,
            'is_deleted' => 0,
            'LIMIT' => $size,
            'ORDER' => ['id' => 'DESC'],
        ];
        $pidCondition = $pid ? ['profile_id' => $pid] : [];
        $commonConditions = array_merge($commonConditions, $pidCondition);
        $objectCondition = ['object_id[!]' => 0];
        $db = $this->db();

        $activityIds = [
            'prev' => [],
            'current' => [],
            'next' => []
        ];

        // Get activity ids for current page
        $indexCondition = $index ? ['id[<=]' => $index] : [];
        $conditions = array_merge($commonConditions, $indexCondition);
        $selectedColumns = is_null($pid) && $groupActivities ? ['id' => Medoo::raw('max(id)')] : ['id'];
        if (is_null($pid) && $groupActivities) {
            $distinctObjectConditions = array_merge($conditions, $objectCondition);
            $distinctObjectIds = $db->select('activities', '@object_id', $distinctObjectConditions);
            if (empty($distinctObjectIds)) {
                return $activityIds;
            }
            $conditions = array_merge($conditions, ['object_id' => $distinctObjectIds], ['GROUP' => 'object_id']);
        } else {
            $conditions = array_merge($conditions, $objectCondition);
        }
        $currentIds = $db->select('activities', $selectedColumns, $conditions);
        $currentIds = array_map(function ($v) {
            return $v['id'];
        }, $currentIds);
        $activityIds['current'] = $currentIds;

        // Reset timeline pages cache
        $cacheKey = 'timeline_pages';
        $session = $this->session($request);
        if (!isset($session[$cacheKey]) || is_null($index)) {
            $session[$cacheKey] = [];
        }

        // Set current index
        if (!empty($currentIds) && is_null($index)) {
            $index = $currentIds[0];
        }
        // Set last index
        $lastId = empty($currentIds) ? 0 : $currentIds[count($currentIds) - 1];

        // Get index cache
        $cache = array_values($session[$cacheKey]);

        // Get prev index
        if ($index) {
            $i = count($cache) - 1;
            while (isset($cache[$i])) {
                $l = $cache[$i];
                if ($l > $index) {
                    $activityIds['prev'] = [$l];
                    break;
                }
                unset($cache[$i]);
                $i--;
            }
            // Cache current index
            $cache[] = $index;
        }
        // Store page indexes
        $session[$cacheKey] = $cache;

        if ($lastId) {
            // Get next index
            $conditions = array_merge($commonConditions, $objectCondition, [
                'id[<]' => $lastId,
                'LIMIT' => 1,
            ]);
            $nextIds = $db->select('activities', 'id', $conditions);
            $activityIds['next'] = $nextIds;
        }


        return $activityIds;
    }
}
