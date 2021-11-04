<?php

namespace Cherry\Test\Task;

use Cherry\Task\TaskQueue;
use Cherry\Test\TestCase;
use Cherry\Task\DeleteActivityTask;
use Medoo\Medoo;

class TaskQueueTest extends TestCase
{
    public function testQueue()
    {
        $taskQueue = $this->container->get(TaskQueue::class);
        $task = [
            'task' => DeleteActivityTask::class,
            'params' => ['activity_id' => 1],
            'priority' => 111,
        ];
        $id = $taskQueue->queue($task);
        $this->assertTrue($id > 0);
        $taskInDB = $this->container->get(Medoo::class)->get('tasks', '*', ['id' => $id]);
        $this->assertNotEmpty($taskInDB);
        $this->assertEquals(short($task['task']), $taskInDB['task']);
        $this->assertEquals($task['params'], json_decode($taskInDB['params'], true));
        $this->assertEquals($task['priority'], $taskInDB['priority']);

        $task = [
            'task' => 'DeleteActivityTask',
            'params' => ['activity_id' => 1],
            'priority' => 20,
        ];
        $id = $taskQueue->queue($task);
        $this->assertGreaterThan(0, $id);
        $taskInDB = $this->container->get(Medoo::class)->get('tasks', '*', ['id' => $id]);
        $this->assertNotEmpty($taskInDB);
        $this->assertEquals($task['task'], $taskInDB['task']);
        $this->assertEquals($task['params'], json_decode($taskInDB['params'], true));
        $this->assertEquals($task['priority'], $taskInDB['priority']);
    }

    public function testQueueWithInvalidClass()
    {
        $this->expectExceptionMessageMatches('/Invalid task/');
        $taskQueue = $this->container->get(TaskQueue::class);
        $fakeTask = [
            'task' => TaskQueue::class,
            'params' => ['activity_id' => 1]
        ];
        $taskQueue->queue($fakeTask);
    }

    public function testQueueWithUnregisterCommand()
    {
        $this->expectExceptionMessageMatches('/Task not found/');
        $taskQueue = $this->container->get(TaskQueue::class);
        $fakeTask = [
            'task' => 'FakeTask',
            'params' => ['activity_id' => 1]
        ];
        $taskQueue->queue($fakeTask);
    }

    public function testQueueArray()
    {
        $tasks = [
            [
                'task' => 'DeleteActivityTask',
                'params' => ['activity_id' => 1],
                'priority' => 20,
            ],
            [
                'task' => 'DeleteActivityTask',
                'params' => ['activity_id' => 2],
            ]
        ];
        $taskQueue = $this->container->get(TaskQueue::class);
        $count = $taskQueue->queueArray($tasks);
        $this->assertEquals(2, $count);
        $this->assertEquals(2, $taskQueue->rowCount());
        $this->assertEquals(0, $taskQueue->lastTaskId());

    }
}
