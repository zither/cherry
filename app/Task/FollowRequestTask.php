<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\RetryException;
use adrianfalleiro\TaskInterface;
use GuzzleHttp\Exception\GuzzleException;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;
use PDOException;

class FollowRequestTask implements TaskInterface
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

        // Follow activity from Zap slave servers have invalid actors
        $idHost = parse_url($rawActivity['id'], PHP_URL_HOST);
        $actorHost = parse_url($actor, PHP_URL_HOST);
        if ($idHost !== $actorHost) {
            throw new FailedTaskException(sprintf(
                'Hosts do not match: id host: %s, actor host: %s',
                $idHost,
                $actorHost,
            ));
        }

        $profile = $db->get('profiles', '*', ['actor' => $actor]);

        try {
            if (empty($profile)) {
                $profile = (new FetchProfileTask($this->container))->command(['actor' => $actor]);
            }
            $followed = $db->count('followers', [
                'profile_id' => $profile['id'],
                'status' => 1,
            ]);
            // 已经关注，结束流程
            if ($followed) {
                return;
            }
            // 插入通知
            $notification = [
                'actor' => $actor,
                'profile_id' => $profile['id'],
                'activity_id' => $activityId,
                'type' => 'Follow',
                'viewed' => 0,
            ];
            $db->insert('notifications', $notification);
        } catch (GuzzleException $e) {
            throw new RetryException($e->getMessage());
        } catch (PDOException $e) {
            throw new RetryException();
        }
    }
}