<?php

namespace Cherry\Middleware;

use Medoo\Medoo;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use GuzzleHttp\Psr7\Response;

class InitialMiddleware implements MiddlewareInterface
{
    protected $container;

    /**
     * Constructor
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function process(Request $request, RequestHandler $requestHandler): ResponseInterface
    {
        $db = $this->container->get(Medoo::class);
        $settings = $this->container->make('settings');
        $profileExists = $db->count('profiles', ['id' => 1]);

        if (!empty($settings) && !$profileExists) {
            return $requestHandler->handle($request);
        }

        return new Response('302', ['location' => '/']);
    }
}