<?php

namespace Cherry\Task\Cron;

use Exception;
use adrianfalleiro\TaskInterface;
use Cherry\Helper\SignRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class UpdateRemotePollsTask implements TaskInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function command(array $args)
    {
        $db = $this->container->get(Medoo::class);
        $poll = $db->get('polls', ['id', 'activity_id', 'object_id', 'multiple', 'end_time'], ['is_closed' => 0, 'ORDER' => [
            'updated_at' => 'ASC'
        ]]);
        if (empty($poll)) {
            return;
        }

        $object = $db->get('objects', ['raw_object_id'], ['id' => $poll['object_id']]);
        // Skip invalid poll
        if (empty($object)) {
            $db->update('polls', ['is_closed' => 1], ['id' => $poll['id']]);
            return;
        }

        try {
            $helper = $this->container->get(SignRequest::class);
            $request = new Request('GET', $object['raw_object_id'], [
                'Accept' => 'application/activity+json',
                'Content-Type' => 'text/html',
            ]);
            $request = $helper->sign($request);
            $client = new Client();
            $response = $client->send($request);
            $data = $response->getBody()->getContents();
            $question = json_decode($data, true);
            if (empty($question)) {
                throw new Exception('Question not found');
            }
            $choicesKey = $poll['multiple'] ? 'anyOf' : 'oneOf';
            $choices = [];
            foreach ($question[$choicesKey] as $v) {
                $choices[] = [
                    'type' => $v['type'],
                    'name' => $v['name'],
                    'count' => $v['replies']['totalItems'] ?? 0,
                ];
            }
            $updated = [
                'choices' => json_encode($choices, JSON_UNESCAPED_UNICODE),
                'voters_count' => $question['votersCount'] ?? 0,
                'is_closed' => strtotime($poll['end_time']) < time() ? 1 : 0,
            ];
            $db->update('polls', $updated, ['id' => $poll['id']]);
        } catch (Exception $e) {
            $db->update('polls', ['updated_at' => date('Y-m-d H:i:s')], ['id' => $poll['id']]);
        }
    }
}