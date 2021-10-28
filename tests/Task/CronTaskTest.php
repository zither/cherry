<?php

namespace Cherry\Test\Task;

use Cherry\Task\CronTask;
use Cherry\Test\Traits\SetupCherryEnv;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;

class CronTaskTest extends TestCase
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

    public function testCommandWithTaskThatThrowsUnexpectedException()
    {
        $invalidLocalUndoTask = [
            'task' => 'LocalUndoTask',
            'params' => json_encode(['activity_id' => null]),
            'priority' => 140,
        ];
        $db = $this->container->get(Medoo::class);
        $db->insert('tasks', $invalidLocalUndoTask);
        $cronTask = new CronTask($this->container);
        $cronTask->command([]);
        $log = $db->get('task_logs', '*', ['task' => 'LocalUndoTask']);
        $this->assertEquals('Invalid activity id: ', $log['reason']);
    }
}
