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

        $idHost = parse_url($rawActivity['id'], PHP_URL_HOST);
        $actorHost = parse_url($actor, PHP_URL_HOST);
        $isAlias = $idHost !== $actorHost;

        try {
            if ($isAlias) {
                $realProfileId = $db->get('actor_aliases', 'profile_id', [
                    'alias' => $actor,
                    'real_host' => $idHost
                ]);
                if ($realProfileId) {
                    $profile = $db->get('profiles', '*', ['id' => $realProfileId]);
                    $actor = $profile['actor'];
                } else if (isset($rawActivity['signature']['creator'])) {
                    $actor = $rawActivity['signature']['creator'];
                    $profile = $db->get('profiles', '*', ['actor' => $actor]);
                } else {
                    throw new FailedTaskException('Invalid alias: ' . $actor);
                }
            } else {
                $profile = $db->get('profiles', '*', ['actor' => $actor]);
            }
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