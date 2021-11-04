<?php

namespace Cherry\Test\Task;

use Cherry\Task\TaskFactory;
use Cherry\Test\TestCase;
use Cherry\Task\DeleteActivityTask;
use Medoo\Medoo;

class TaskFactoryTest extends TestCase
{
    public function testQueue()
    {
        $taskFactory = $this->container->get(TaskFactory::class);
        $id = $taskFactory->queue(DeleteActivityTask::class, ['activity_id' => 1], 111);
        $this->assertGreaterThan(0, $id);
        $task = $this->container->get(Medoo::class)->get('tasks', '*', ['id' => $id]);
        $this->assertNotEmpty($task);
        $this->assertEquals(short(DeleteActivityTask::class), $task['task']);
        $this->assertEquals(['activity_id' => 1], json_decode($task['params'], true));
        $this->assertEquals(111, $task['priority']);

        $id = $taskFactory->queue('DeleteActivityTask', ['activity_id' => 1], 20);
        $this->assertGreaterThan(0, $id);
        $task = $this->container->get(Medoo::class)->get('tasks', '*', ['id' => $id]);
        $this->assertNotEmpty($task);
        $this->assertEquals(short(DeleteActivityTask::class), $task['task']);
        $this->assertEquals(['activity_id' => 1], json_decode($task['params'], true));
        $this->assertEquals(20, $task['priority']);
    }

    public function testQueueWithInvalidClass()
    {
        $this->expectExceptionMessageMatches('/Invalid task/');
        $taskFactory = $this->container->get(TaskFactory::class);
        $taskFactory->queue(TaskFactory::class, ['activity_id' => 1], 111);
    }

    public function testQueueWithUnregisterCommand()
    {
        $this->expectExceptionMessageMatches('/Task not found/');
        $taskFactory = $this->container->get(TaskFactory::class);
        $taskFactory->queue('FakeTask', ['activity_id' => 1], 111);
    }
}
