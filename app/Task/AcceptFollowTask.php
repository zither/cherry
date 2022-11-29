<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\ActivityPub\ActivityPub;
use Cherry\ActivityPub\Context;
use Cherry\Helper\SignRequest;
use Cherry\Helper\Time;
use Godruoyi\Snowflake\Snowflake;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class AcceptFollowTask implements TaskInterface
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
        $profileId = $args['profile_id'];
        $profile = $db->get('profiles', ['inbox'], ['id' => $profileId]);

        $adminProfile = $db->get('profiles', ['actor'], ['id' => CHERRY_ADMIN_PROFILE_ID]);

        if (empty($activity) || empty($profile)) {
            throw new FailedTaskException('Both activity and profile required');
        }

        $rawActivity = json_decode($activity['raw'], true);
        $snowflake = $this->container->get(Snowflake::class);
        $settings = $this->container->make('settings');
        $acceptActivityId = $snowflake->id();
        $message = [
            'id' => sprintf('https://%s/activities/%s', $settings['domain'], $acceptActivityId),
            'actor' => $adminProfile['actor'],
            'type' => ActivityPub::ACCEPT,
            'object' => $rawActivity,
        ];
        $message = Context::set($message, Context::OPTION_ACTIVITY_STREAMS);
        $helper = $this->container->get(SignRequest::class);
        $message['signature'] = $helper->createLdSignature($message);
        $acceptActivity = [
            'activity_id' => $message['id'],
            'profile_id' => CHERRY_ADMIN_PROFILE_ID,
            'type' => ActivityPub::ACCEPT,
            'raw' => json_encode($message, JSON_UNESCAPED_SLASHES),
            'published' => Time::getLocalTime(),
        ];
        $db->insert('activities', $acceptActivity);
        $newId = $db->id();
        $request = new Request('POST', $profile['inbox'], [
            'Content-type' => 'application/activity+json',
            'Accept' => 'application/activity+json',
        ]);
        $request->getBody()->write(json_encode($message, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        $request = $helper->sign($request);
        $client = new Client();
        $response = $client->send($request);
        if ($response->getStatusCode() >= 400) {
            throw new FailedTaskException((string)$response->getBody());
        }

        $db->insert('followers', [
            'profile_id' => $profileId,
            'status' => 1,
            'follow_activity_id' => $activityId,
            'accept_activity_id' => $newId,
            'created_at' => $acceptActivity['published'],
        ]);
    }
}