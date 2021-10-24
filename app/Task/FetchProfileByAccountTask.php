<?php

namespace Cherry\Task;

use adrianfalleiro\RetryException;
use adrianfalleiro\FailedTaskException;
use adrianfalleiro\TaskInterface;
use GuzzleHttp\Exception\GuzzleException;
use Cherry\Helper\SignRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class FetchProfileByAccountTask implements TaskInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param array $args
     * @return array|false|mixed
     * @throws RetryException
     * @throws GuzzleException
     * @throws FailedTaskException
     */
    public function command(array $args)
    {
        $account = $args['account'];
        $db = $this->container->get(Medoo::class);

        $account = ltrim($account, '@');
        $profile = $db->get('profiles', '*', ['account' => $account]);

        if (empty($profile)) {
            $signHttp = $this->container->get(SignRequest::class);
            $client = new Client();

            $accountArr = explode('@', $account);
            if (!empty($args['mock_server'])) {
                $webFingerUrl = $args['mock_server'];
            } else {
                $webFingerUrl = sprintf('https://%s/.well-known/webfinger?resource=acct:%s', $accountArr[1], $account);
            }
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
                    $actor = $v['href'];
                    break;
                }
            }
            if (empty($actor)) {
                throw new RetryException("Profile link for $account not found");
            }

            $profile = (new FetchProfileTask($this->container))->command(['actor' => $actor]);
        }

        return $profile;
    }
}
