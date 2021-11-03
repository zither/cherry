<?php

namespace Cherry\Task;

use Medoo\Medoo;
use Psr\Container\ContainerInterface;
use adrianfalleiro\TaskInterface;
use InvalidArgumentException;

class Factory
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var string
     */
    protected $table = 'tasks';

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function queue(string $taskName, array $params = [], int $priority = 140): int
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
        $db = $this->container->get(Medoo::class);
        $db->insert($this->table, [
            'task' => $taskName,
            'params' => json_encode($params, JSON_UNESCAPED_UNICODE),
            'priority' => $priority
        ]);
        return $db->id();
    }
}
