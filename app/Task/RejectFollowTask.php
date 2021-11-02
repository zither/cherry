<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\RetryException;
use adrianfalleiro\TaskInterface;
use Cherry\Helper\SignRequest;
use Godruoyi\Snowflake\Snowflake;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class RejectFollowTask implements TaskInterface
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

        if (empty($activity) || empty($profile)) {
            throw new FailedTaskException('Both activity and profile required');
        }

        $rawActivity = json_decode($activity['raw'], true);

        $snowflake = $this->container->get(Snowflake::class);
        $settings = $this->container->make('settings');
        $message = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => sprintf('https://%s/outbox/%s', $settings['domain'], $snowflake->id()),
            'actor' => sprintf('https://%s', $settings['domain']),
            'type' => 'Reject',
            'object' => $rawActivity,
        ];

        $helper = $this->container->get(SignRequest::class);
        $message['signature'] = $helper->createLdSignature($message);

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

        $db->delete('activities', ['id' => $activityId]);
    }
}