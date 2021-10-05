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

    /**
     * @param $Line
     * @return array|void
     */
    protected function blockHeader($Line)
    {
        return;
    }

    public function text($text)
    {
        $marked = parent::text($text);
        $pattern = '/#([^#<\s]+)/';
        preg_match_all($pattern, $marked, $matches);

        if (empty($matches[1])) {
            return $marked;
        }
        $this->tags = $matches[1];
        $replacement = sprintf('<a class="mention hashtag" href="https://%s/tags/${1}" rel="tag">#${1}</a>', $this->host);
        return preg_replace('/#([^#<\s]+)/', $replacement, $marked);
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