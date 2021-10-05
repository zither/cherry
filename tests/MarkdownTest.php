<?php

use PHPUnit\Framework\TestCase;
use Cherry\Markdown;

class MarkdownTest extends TestCase
{
    public function testSetTagHost()
    {
        $tag = 'tag';
        $markup = '#' . $tag;
        $host = 'chokecherry.cc';
        $target = sprintf(
            '<p><a class="mention hashtag" href="https://%s/tags/%s" rel="tag">%s</a></p>',
            $host,
            $tag,
            $markup
        );
        $parser = new Markdown();
        $parser->setTagHost($host);
        $html = $parser->text($markup);
        $this->assertEquals($target, $html);
    }
}