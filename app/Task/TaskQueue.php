<?php

namespace Cherry\Task;

use Medoo\Medoo;
use Psr\Container\ContainerInterface;
use adrianfalleiro\TaskInterface;
use InvalidArgumentException;

class TaskQueue
{
    const DEFAULT_PRIORITY = 140;
    const DEFAULT_DELAY = 60;
    const DEFAULT_RETRIES = 3;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var string
     */
    protected $table = 'tasks';

    /**
     * @var array
     */
    protected $tasks = [];

    /**
     * @var int
     */
    protected $lastTaskId = 0;

    /**
     * @var int
     */
    protected $rowCount = 0;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function queue(array $task): int
    {
        $this->clearTasks();
        $this->pushTask($this->filterTask($task));
        $success = $this->store();
        return $success ? $this->lastTaskId : 0;
    }

    public function delay(array $task, int $delaySeconds): int
    {
        $this->clearTasks();
        $task['timer'] = date('Y-m-d H:i:s', time() + $delaySeconds);
        $this->pushTask($this->filterTask($task));
        $success = $this->store();
        return $success ? $this->lastTaskId : 0;
    }

    public function queueArray(array $tasks): int
    {
        $this->clearTasks();
        foreach ($tasks as $task) {
            $this->pushTask($this->filterTask($task));
        }
        $this->store();
        return $this->rowCount();
    }

    public function rowCount(): int
    {
        return $this->rowCount;
    }

    public function lastTaskId(): int
    {
        return $this->lastTaskId;
    }

    protected function filterTask(array $task): array
    {
        $taskData = [
            'task' => $this->filterTaskName($task['task']),
            'params' => $task['params'] ?? '',
            'retried' => $task['retried'] ?? 0,
            'max_retries' => $task['max_retries'] ?? self::DEFAULT_RETRIES,
            'priority' => $task['priority'] ?? self::DEFAULT_PRIORITY,
            'delay' => $task['delay'] ?? self::DEFAULT_DELAY,
            'timer' => $task['timer'] ?? date('Y-m-d H:i:s'),
            'is_loop' => $task['is_loop'] ?? 0 ,
        ];
        return $taskData;
    }

    protected function filterTaskName(string $taskName): string
    {
        if (class_exists($taskName)) {
            $interfaces = class_implements($taskName);
            if (!isset($interfaces[TaskInterface::class])) {
                throw new InvalidArgumentException('Invalid task: ' . $taskName);
            }
            $taskName = short($taskName);
        } else {
            $commands = $this->container->get('commands');
            if (!isset($commands[$taskName])) {
                throw new InvalidArgumentException('Task not found: ' . $taskName);
            }
        }
        return $taskName;
    }

    protected function clearTasks()
    {
        $this->tasks = [];
        $this->lastTaskId = 0;
        $this->rowCount = 0;
    }

    protected function pushTask(array $task)
    {
        if (is_array($task['params'])) {
            $task['params'] = json_encode($task['params'], JSON_UNESCAPED_UNICODE);
        }
        $this->tasks[] = $task;
    }

    protected function store(): bool
    {
        if (!empty($this->tasks)) {
            $db = $this->container->get(Medoo::class);
            if (count($this->tasks) === 1) {
                $statement = $db->insert($this->table, $this->tasks[0]);
                $this->lastTaskId = $db->id();
                $this->rowCount = $statement->rowCount();
            } else {
                $statement = $db->insert($this->table, $this->tasks);
                $this->rowCount = $statement->rowCount();
            }
            return $this->rowCount > 0;
        }
        return false;
    }
}
