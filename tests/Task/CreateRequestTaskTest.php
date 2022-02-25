<?php

namespace Cherry\Test\Task;

use DateTimeZone;
use DateTime;
use Cherry\ActivityPub\Activity;
use Cherry\ActivityPub\ActivityPub;
use Cherry\Helper\Time;
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
        $rawPollActivity = file_get_contents(ROOT . '/tests/data/poll-activity.json');
        $pollActivity = Activity::createFromArray(json_decode($rawPollActivity, true));
        $activity = [
            'activity_id' => $pollActivity->id,
            'profile_id' => 1,
            'object_id' => 0,
            'type' => $pollActivity->type,
            'raw' => $rawPollActivity,
            'is_local' => 1,
            'is_public' => $pollActivity->isPublic(),
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

    public function testTimestampInDB()
    {
        $rawPollActivity = file_get_contents(ROOT . '/tests/data/poll-activity.json');
        $pollActivity = Activity::createFromArray(json_decode($rawPollActivity, true));
        $activity = [
            'activity_id' => $pollActivity->id,
            'profile_id' => 1,
            'object_id' => 0,
            'type' => $pollActivity->type,
            'raw' => $rawPollActivity,
            'is_local' => 1,
            'is_public' => $pollActivity->isPublic(),
        ];
        $db = $this->container->get(Medoo::class);
        $db->insert('activities', $activity);
        $activityId = $db->id();
        $task = new CreateRequestTask($this->container);
        $task->command(['activity_id' => $activityId]);

        $objectId = $db->get('activities', 'object_id', ['id' => $activityId]);
        $this->assertNotEmpty($objectId);
        $poll = $db->get('polls', '*', ['object_id' => $objectId]);
        $this->assertNotEmpty($poll);

        $configs = $this->container->get('configs');
        $endTime = $pollActivity->object['endTime'];
        $datetime = new DateTime($endTime);
        $this->assertEquals('Z', $datetime->getTimezone()->getName());
        $datetime->setTimezone(new DateTimeZone($configs['default_time_zone']));
        $this->assertEquals($configs['default_time_zone'], $datetime->getTimezone()->getName());
        $localTime = $datetime->format('Y-m-d H:i:s');
        $this->assertEquals($localTime, $poll['end_time']);

        $statement = $db->exec('SELECT @@session.time_zone;');
        $dbTimezone = $statement->fetch(\PDO::FETCH_NUM)[0];
        $this->assertEquals(Time::getTimeZoneOffset($configs['default_time_zone']), $dbTimezone);
    }

    public function testDuplicateActivity()
    {
        $rawEmojiActivity = file_get_contents(ROOT . '/tests/data/emoji_activity.json');
        $emojiActivity = Activity::createFromArray(json_decode($rawEmojiActivity, true));
        $activity = [
            'activity_id' => $emojiActivity->id,
            'profile_id' => 1,
            'object_id' => 999,
            'type' => $emojiActivity->type,
            'raw' => $rawEmojiActivity,
            'is_local' => 1,
            'is_public' => $emojiActivity->isPublic(),
        ];
        $db = $this->container->get(Medoo::class);
        $db->insert('activities', $activity);

        $activity['object_id'] = 0;
        $db->insert('activities', $activity);
        $activityId = $db->id();

        $task = new CreateRequestTask($this->container);
        $task->command(['activity_id' => $activityId]);

        $updatedActivity = $db->get('activities', '*', ['id' => $activityId]);
        $this->assertNotEmpty($updatedActivity);
        $this->assertEquals(0, $updatedActivity['object_id']);
        $this->assertEquals(1, $updatedActivity['is_deleted']);
    }
}
