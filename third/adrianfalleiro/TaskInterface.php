<?php

namespace adrianfalleiro;

use Psr\Container\ContainerInterface;

interface TaskInterface
{
    public function __construct(ContainerInterface $container);

    public function command(array $args);
}