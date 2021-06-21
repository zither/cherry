<?php

namespace adrianfalleiro;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use \RuntimeException;
use \ReflectionClass;
use \Exception;
use Slim\Factory\AppFactory;

/**
 * Slim PHP 4 CLI task runner
 *
 * @package SlimCLIRunner
 * @author  Adrian Falleiro <adrian@falleiro.com>
 * @license MIT http://www.opensource.org/licenses/mit-license.php
 */
class SlimCLIRunner
{
    /**
     * @var array
     */
    protected $args;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Constructor
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container, array $args)
    {
        $this->container = $container;
        $this->args = $args;
    }

    /**
     * Called when the class is invoked
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     */
    public function __invoke($request, $handler)
    {
        if (PHP_SAPI !== 'cli') {
            return $handler->handle($request);
        }

        try {
            $response = $this->createResponse();
            if (count($this->args) < 2) {
                throw new RuntimeException("Task name required!\n");
            }

            $command = $this->args[1];
            $args = array_slice($this->args, 2);
            $possible_commands = $this->container->get('commands');

            if (!array_key_exists($command, $possible_commands)) {
                throw new RuntimeException("Command not found\n");
            }

            $class = $possible_commands[$command];
            if (!class_exists($class)) {
                throw new RuntimeException(sprintf("Class %s does not exist\n", $class));
            }

            $task_class = new ReflectionClass($class);
            if (!$task_class->implementsInterface(TaskInterface::class)) {
                throw new RuntimeException(sprintf("Class %s does not implement TaskInterface\n", $class));
            }

            $task = $task_class->newInstance($this->container);
            $task->command($args);
            return $response->withStatus(200);
        } catch(Exception $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(500);
        }
    }

    protected function createResponse(): ResponseInterface
    {
        if ($this->container->has(ResponseFactoryInterface::class)) {
            $responseFactory = $this->container->get(ResponseFactoryInterface::class);
        } else {
            $responseFactory = AppFactory::determineResponseFactory();
        }
        return $responseFactory->createResponse();
    }
}
