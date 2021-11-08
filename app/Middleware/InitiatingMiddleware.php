<?php

namespace Cherry\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use GuzzleHttp\Psr7\Response;

class InitiatingMiddleware implements MiddlewareInterface
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
        $target = $request->getRequestTarget();
        $settings = $this->container->make('settings', ['keys' => ['domain']]);
        $initiated = !empty($settings);
        if ($initiated) {
            if ($target === '/init') {
                return new Response('302', ['location' => '/']);
            }
        } else {
            if ($target !== '/init') {
                return new Response('302', ['location' => '/init']);
            }
        }
        return $requestHandler->handle($request);
    }
}