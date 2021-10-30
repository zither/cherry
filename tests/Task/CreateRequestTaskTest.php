<?php

namespace Cherry\Test\Task;

use Cherry\ActivityPub\Activity;
use Cherry\ActivityPub\ActivityPub;
use Cherry\Task\CreateRequestTask;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Cherry\Test\Traits\SetupCherryEnv;

class CreateRequestTaskTest extends TestCase
{
    use SetupCherryEnv;

    public function setUp(): void
    {
        $this->setUpCherryEnv();
    }

    public function tearDown(): void
    {
        $this->tearDownCherryEnv();
    }

    public function testCommandUsingEmojiActivity()
    {
        $rawEmojiActivity = file_get_contents(ROOT . '/tests/data/emoji_activity.json');
        $emojiActivity = Activity::createFromArray(json_decode($rawEmojiActivity, true));
        $activity = [
            'activity_id' => $emojiActivity->id,
            'profile_id' => 1,
            'object_id' => 0,
            'type' => $emojiActivity->type,
            'raw' => $rawEmojiActivity,
            'is_local' => 1,
            'is_public' => $emojiActivity->isPublic(),
        ];
        $db = $this->container->get(Medoo::class);
        $db->insert('activities', $activity);
        $activityId = $db->id();
        $task = new CreateRequestTask($this->container);
        $task->command(['activity_id' => $activityId]);

        $objectId = $db->get('activities', 'object_id', ['id' => $activityId]);
        $this->assertNotEmpty($objectId);
        $objectContent = $db->get('objects', 'content', ['id' => $objectId]);
        $this->assertNotEmpty($objectContent);
        $expected = '<img class="emoji" src="https://cherry.test/emojis/1.png" alt=":icon_weibo:" referrerpolicy="no-referrer" />';
        $this->assertStringContainsString($expected, $objectContent);
    }

    public function testCommandUsingPollActivity()
    {
        $rawEmojiActivity = file_get_contents(ROOT . '/tests/data/poll-activity.json');
        $emojiActivity = Activity::createFromArray(json_decode($rawEmojiActivity, true));
        $activity = [
            'activity_id' => $emojiActivity->id,
            'profile_id' => 1,
            'object_id' => 0,
            'type' => $emojiActivity->type,
            'raw' => $rawEmojiActivity,
            'is_local' => 1,
            'is_public' => $emojiActivity->isPublic(),
        ];
        $db = $this->container->get(Medoo::class);
        $db->insert('activities', $activity);
        $activityId = $db->id();
        $task = new CreateRequestTask($this->container);
        $task->command(['activity_id' => $activityId]);

        $objectId = $db->get('activities', 'object_id', ['id' => $activityId]);
        $this->assertNotEmpty($objectId);
        $objectType = $db->get('objects', 'type', ['id' => $objectId]);
        $this->assertEquals($objectType, ActivityPub::OBJECT_TYPE_QUESTION);
        $poll = $db->get('polls', '*', ['object_id' => $objectId]);
        $this->assertNotEmpty($poll);
        $choices = [
            ['type' => 'Note', 'name' => '选项1', 'count' => 0],
            ['type' => 'Note', 'name' => '选项2', 'count' => 0],
        ];
        $this->assertEquals( $choices, json_decode($poll['choices'], true));
    }
}
