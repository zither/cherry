<?php

namespace Cherry\Test\Task;

use Cherry\Task\FetchObjectTask;
use Cherry\Test\TestCase;

class FetchObjectTaskTest extends TestCase
{
    public function testTagType()
    {

        $task = new FetchObjectTask($this->container);
        $tag = [
            "id" => "https://slashine.onl/tags/pixelfed",
            "name" => "#pixelfed"
        ];
        $this->assertEquals('HashTag', $task->tagType($tag));

        $tag = [
            "type" => "Mention",
            "href" => "https://ovo.st/club/board",
            "name" => "@board@ovo.st"
        ];
        $this->assertEquals('Mention', $task->tagType($tag));

        $tag = [
            'id' => 'https://cherry.test/invalid-tag/1',
            'name' => 'invalid'
        ];
        $this->assertEquals('Unknown', $task->tagType($tag));
    }
}
