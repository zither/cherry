<?php

namespace Cherry\Test;

use PHPUnit\Framework\TestCase;
use Cherry\Markdown;

class MarkdownTest extends TestCase
{
    public function testSetTagHost()
    {
        $tag = 'tag';
        $markup = '#' . $tag;
        $host = 'cherry.test';
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

    public function testInlineHashTag()
    {
        $host = 'cherry.test';
        $markup = '标签测试 #cherry some chars here.';
        $parser = new Markdown();
        $parser->setTagHost($host);
        $html = $parser->text($markup);
        $expected = sprintf(
            '<p>标签测试 <a class="mention hashtag" href="https://%s/tags/cherry" rel="tag">#cherry</a> some chars here.</p>',
            $host
        );
        $this->assertEquals($expected, $html);
        $this->assertEquals(['cherry'], $parser->hashTags());
    }
}