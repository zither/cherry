<?php

namespace Cherry\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use GuzzleHttp\Psr7\Response;

class LockSiteMiddleware implements MiddlewareInterface
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
        $keys = ['lock_site'];
        $settings = $this->container->make('settings', ['keys' => $keys]);
        if ($settings['lock_site'] ?? false) {
            return new Response('302', ['location' => '/login']);
        }
        return $requestHandler->handle($request);
    }
}