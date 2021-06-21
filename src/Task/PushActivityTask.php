<?php

namespace Cherry\Task;

use Exception;
use adrianfalleiro\FailedTaskException;
use adrianfalleiro\RetryException;
use adrianfalleiro\TaskInterface;
use Cherry\Helper\SignRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class PushActivityTask implements TaskInterface
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
        if (empty($activity)) {
            throw new FailedTaskException("Invalid activity id: $activityId");
        }
        $inbox = $args['inbox'];
        $request = new Request('POST', $inbox, [
            'Accept' => 'application/activity+json',
            'Content-Type' => 'application/activity+json',
        ]);
        $request->getBody()->write($activity['raw']);

        $helper = $this->container->get(SignRequest::class);
        $request = $helper->sign($request);
        try {
            $response = (new Client())->send($request);
            if ($response->getStatusCode() >= 400) {
                throw new RetryException('Response status code: ' . $response->getStatusCode());
            }
        } catch (Exception $e) {
            throw new RetryException('Request lost: ' . $e->getCode());
        }
    }
}