<?php

namespace Cherry\Task\Cron;

use adrianfalleiro\TaskInterface;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class DeleteExpiredSessionsTask implements TaskInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function command(array $args)
    {
        $db = $this->container->get(Medoo::class);
        $expiredAt = date('Y-m-d H:i:s', strtotime('-2 hours'));
        $db->delete('sessions', [
            'updated_at[<]' => $expiredAt,
            'content' => 'a:0:{}',
        ]);
    }
}