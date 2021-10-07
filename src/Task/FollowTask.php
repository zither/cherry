<?php

namespace Cherry\Task;

use adrianfalleiro\RetryException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\Activity;
use Cherry\Helper\SignRequest;
use Cherry\Helper\Time;
use Godruoyi\Snowflake\Snowflake;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;
use PDOException;

class FollowTask implements TaskInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function command(array $args)
    {
        try {
            $account = $args['account'];
            $db = $this->container->get(Medoo::class);
            $signHttp = $this->container->get(SignRequest::class);
            $client = new Client();

            if ($args['is_url']) {
                $profileUrl = $account;
            } else {
                $accountArr = explode('@', $account);
                $webFingerUrl = sprintf('https://%s/.well-known/webfinger?resource=acct:%s', $accountArr[1], $account);

                $webFingerRequest = new Request('GET', $webFingerUrl, [
                    'Accept' => 'application/activity+json',
                    'Content-Type' => 'text/html',
                ]);

                $webFingerRequest = $signHttp->sign($webFingerRequest);
                $response = $client->send($webFingerRequest);

                $info = json_decode($response->getBody()->getContents(), true);
                if (empty($info['links'])) {
                    throw new RetryException("Links for $account not found");
                }
                foreach ($info['links'] as $v) {
                    if ($v['rel'] === 'self') {
                        $profileUrl = $v['href'];
                        break;
                    }
                }
                if (empty($profileUrl)) {
                    throw new RetryException("Profile link for $account not found");
                }
            }

            $profile = $db->get('profiles', '*', ['actor' => $profileUrl]);

            if (empty($profile)) {
                $profile = (new FetchProfileTask($this->container))->command(['actor' => $profileUrl]);
            }

            $adminProfile = $db->get('profiles', ['id', 'outbox', 'actor'], ['id' =>  1]);

            $db->pdo->beginTransaction();
            $snowflake = $this->container->get(Snowflake::class);
            $activityId = $snowflake->id();
            $followRequest = [
                "@context" => [
                    "https://www.w3.org/ns/activitystreams",
                    "https://w3id.org/security/v1",
                ],
                'id' => "{$adminProfile['outbox']}/$activityId",
                'type' => 'Follow',
                'actor' => $adminProfile['actor'],
                //@TODO add profile type to profile table !!!
                'object' => $args['is_url'] && $profile['preferred_name'] === 'relay' ? Activity::PUBLIC_COLLECTION : $profileUrl,
            ];
            $jsonRequest =json_encode($followRequest, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $activity = [
                'activity_id' => $followRequest['id'],
                'type' => 'Follow',
                'raw' => $jsonRequest,
                'published' => Time::utc(),
                'is_local' => 1,
            ];
            $db->insert('activities', $activity);
            $db->pdo->commit();
            $request = new Request('POST', $profile['inbox'], ['Content-Type' => 'application/activity+json']);
            $request->getBody()->write($jsonRequest);
            $request = $signHttp->sign($request);

            $response  = $client->send($request);

        } catch (GuzzleException $e) {
            throw new RetryException($e->getMessage());
        } catch (PDOException $e) {
            $db->pdo->rollBack();
            throw new RetryException($e->getMessage());
        }
    }
}