<?php
namespace Cherry\Task;

use Exception;
use adrianfalleiro\FailedTaskException;
use adrianfalleiro\RetryException;
use adrianfalleiro\TaskInterface;
use Cherry\Helper\Time;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class CronTask implements TaskInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function command(array $args)
    {
        $db = $this->container->get(Medoo::class);
        $existsTasks = $this->container->get('commands');
        if (empty($existsTasks)) {
            return;
        }

        $task = $db->get('tasks', '*', [
            'ORDER' => [
                'timer' => 'ASC',
                // 数值越大优先级越高
                'priority' => 'DESC',
                // 未执行过的任务优先级高于执行失败的
                'retried' => 'ASC',
            ],
            'LIMIT' => 1
        ]);
        if (empty($task)) {
            return;
        }

        if (time() < strtotime($task['timer'])) {
            return;
        }

        try {
            try {
                if (!isset($existsTasks[$task['task']])) {
                    throw new FailedTaskException(sprintf('Task %s not defined', $task['task']));
                }
                $params = empty($task['params']) ? [] : json_decode($task['params'], true);
                $class = $existsTasks[$task['task']];
                /** @var TaskInterface $taskObject */
                $taskObject = new $class($this->container);
                $taskObject->command($params);

                if ($task['is_loop']) {
                    $db->update('tasks', [
                        'timer' => Time::delay(($task['retried'] + 1)  * $task['delay']),
                    ], ['id' => $task['id']]);
                } else {
                    // 执行完成，删除任务
                    $db->delete('tasks', ['id' => $task]);
                }
            } catch (RetryException $e) {
                if ($task['retried'] < $task['max_retries']) {
                    $db->update('tasks', [
                        'timer' => Time::delay(($task['retried'] + 1)  * $task['delay']),
                        'retried[+]' => 1,
                    ], ['id' => $task['id']]);
                } else {
                    throw new FailedTaskException($e->getMessage());
                }
            } catch (Exception $e) {
                throw new FailedTaskException($e->getMessage());
            }
            echo "{$task['task']} ran successfully\n";
        } catch (FailedTaskException $e) {
            $db->delete('tasks', ['id' => $task['id']]);
            unset($task['id']);
            $log = array_merge($task, [
                'status' => 2,
                'reason' => $e->getMessage(),
            ]);
            $db->insert('task_logs', $log);
            $logId = $db->id();
            echo "{$task['task']} failed, log id: {$logId}\n";
        }
    }
}
