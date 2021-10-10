<?php

namespace Cherry;

use Parsedown;
use Cherry\Task\FetchProfileByAccountTask;
use Psr\Container\ContainerInterface;

class Markdown extends Parsedown
{
    /**
     * @var string
     */
    protected $host;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var string[]
     */
    protected $tags = [];

    /**
     * @var array
     */
    protected $mentions = [];

    public function __construct()
    {
        $this->InlineTypes['#'][] = 'HashTag';
        $this->inlineMarkerList .= '#';

        $this->InlineTypes['@'][] = 'Mention';
        $this->inlineMarkerList .= '@';
    }

    protected function inlineHashTag($excerpt)
    {
        if (preg_match('/#([^#<\s]+)/', $excerpt['text'], $matches)) {
            $this->tags[] = $matches[1];
            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'a',
                    'text' => $matches[0],
                    'attributes' => [
                        'class' => 'mention hashtag',
                        'href' => sprintf('https://%s/tags/%s', $this->host, $matches[1]),
                        'rel' => 'tag'
                    ]
                ]
            ];
        }
        return null;
    }

    protected function inlineMention($excerpt)
    {
        $pattern = "/((@[_a-z0-9-]+(?:\.[_a-z0-9-]+)*)@[a-z0-9-]+(?:\.[a-z0-9-]+)*(?:\.[a-z]{2,}))/i";
        if (preg_match($pattern, $excerpt['text'], $matches)) {
            $mention = $this->getUserURL($matches[1]);
            if (empty($mention)) {
                return null;
            }
            $this->mentions[] = $mention;
            $url = $mention['url'];
            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'a',
                    'text' => $matches[2],
                    'attributes' => [
                        'class' => 'mention',
                        'href' => $url,
                    ]
                ]
            ];
        }
        return null;
    }

    /**
     * Block Atx Header elements
     *
     * @param $Line
     * @return array|void
     */
    protected function blockHeader($Line)
    {
        return;
    }

    /**
     * Block Setext Header elements
     *
     * @param $Line
     * @param array|null $Block
     * @return array|void|null
     */
    protected function blockSetextHeader($Line, array $Block = null)
    {
        return;
    }

    public function hashTags()
    {
        return $this->tags;
    }

    public function setTagHost(string $host)
    {
        $this->host = $host;
        return $this;
    }

    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    protected function getUserURL(string $account): array
    {
        try {
            $task = new FetchProfileByAccountTask($this->container);
            $profile = $task->command(['account' => $account]);
            return [
                'actor' => $profile['actor'],
                'url' => $profile['url'],
                'account' => $profile['account']
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getMentions(): array
    {
        return $this->mentions;
    }
}
