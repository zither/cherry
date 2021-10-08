<?php

namespace Cherry\Task;

use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use Cherry\Helper\SignRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class FetchProfileTask implements TaskInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function command(array $args)
    {
        $actor = $args['actor'];
        $db = $this->container->get(Medoo::class);
        $targetProfile = $db->get('profiles', ['id'], ['actor' => $actor]);

        $helper = $this->container->get(SignRequest::class);

        $client = new Client();
        $profileRequest = new Request('GET', $actor, [
            'Accept' => 'application/activity+json',
            'Content-Type' => 'text/html',
        ]);

        $profileRequest = $helper->sign($profileRequest);
        $response = $client->send($profileRequest);

        $content = $response->getBody()->getContents();
        $person = json_decode($content, true);
        if (empty($person['inbox']) || empty($person['publicKey'])) {
            throw new FailedTaskException('Invalid actor');
        }
        $host = parse_url($actor, PHP_URL_HOST);
        $profile = [
            'actor' => $actor,
            'type' => $person['type'] ?? 'Person',
            'name' => $person['name'] ?? '',
            'preferred_name' => $person['preferredUsername'],
            'account' => "{$person['preferredUsername']}@$host",
            'url' => $person['url'] ?? '',
            'inbox' => $person['inbox'] ?? '',
            'outbox' => $person['outbox'] ?? '',
            'following' => $person['following'] ?? '',
            'followers' => $person['followers'] ?? '',
            'public_key' => $person['publicKey']['publicKeyPem'] ?? '',
            'likes' => $person['likes'] ?? '',
            'avatar' => $person['icon']['url'] ?? '',
            'summary' => $person['summary'] ?? '',
            'shared_inbox' => $person['endpoints']['sharedInbox'] ?? '',
            'featured' => $person['featured'] ?? '',
        ];
        if (!empty($targetProfile)) {
            $db->update('profiles', $profile, ['actor' => $actor]);
            $profile['id'] = $targetProfile['id'];
        } else {
            $db->insert('profiles', $profile);
            $profile['id'] = $db->id();
        }
        return $profile;
    }
}