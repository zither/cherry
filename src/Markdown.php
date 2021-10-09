<?php

namespace Cherry;

use Parsedown;

class Markdown extends Parsedown
{
    /**
     * @var string
     */
    protected $host;

    protected $tags = [];

    public function __construct()
    {
        $this->InlineTypes['#'][] = 'HashTag';
        $this->inlineMarkerList .= '#';
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
}