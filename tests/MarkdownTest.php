<?php

namespace Cherry\Test;

use Cherry\Test\Traits\MockServer;
use Cherry\Test\Traits\SetupCherryEnv;
use PHPUnit\Framework\TestCase;
use Cherry\Markdown;

class MarkdownTest extends TestCase
{
    use MockServer;
    use SetupCherryEnv;

    public static function setUpBeforeClass(): void
    {
        self::startMockServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopMockerServer();
    }

    public function setUp(): void
    {
        $this->setUpCherryEnv();
    }

    public function tearDown(): void
    {
        $this->tearDownCherryEnv();
    }

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

    public function testInlineMention()
    {
        $markup = sprintf('account: @dev@cherry.test');
        $parser = new Markdown();
        $parser->setContainer($this->container);
        $html = $parser->text($markup);
        $expected = '<p>account: <a class="mention" href="https://cherry.test">@dev</a></p>';
        $this->assertEquals($expected, $html);
        $this->assertEquals([
            [
                'actor' => 'https://cherry.test',
                'url' => 'https://cherry.test',
                'account' => 'dev@cherry.test'
            ]
        ], $parser->getMentions());
    }
}