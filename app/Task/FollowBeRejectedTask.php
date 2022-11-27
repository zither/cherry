<?php

namespace Cherry\Task;

use adrianfalleiro\TaskInterface;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;

class FollowBeRejectedTask implements TaskInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function command(array $args)
    {
        $activityId = $args['activity_id'];
        $db = $this->container->get(Medoo::class);
        $db->update('activities', ['is_deleted' => 1], ['id' => $activityId]);
    }
}