<?php

namespace Cherry\Test\Helper;

use Cherry\Helper\Helper;
use Cherry\Test\PSR7ObjectProvider;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    public function testIsApi()
    {
        $provider = new PSR7ObjectProvider();
        $request = $provider->createServerRequest('/', 'GET');
        $this->assertFalse(Helper::isApi($request));

        $request = $request->withHeader('Accept', 'application/activity+json');
        $this->assertTrue(Helper::isApi($request));
    }

    public function testStripTags()
    {
        $html = file_get_contents(ROOT . '/tests/data/strip_tags.html');
        Helper::stripTags($html);
        // Expect no warning is thrown
        $this->assertTrue(true);
    }

    public function testStripTagsWithUtf8Chars()
    {
        $html = "<p>中文</p>";
        $strippedHtml = Helper::stripTags($html);
        $this->assertStringContainsString('中文', $strippedHtml);
    }
}