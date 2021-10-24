<?php

namespace Cherry\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use GuzzleHttp\Psr7\Response;

class AcceptHeaderMiddleware implements MiddlewareInterface
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
        $headers = $request->getHeader('accept');
        $isApi = false;
        foreach ($headers as $header) {
            if (preg_match('#application/(.+\+)?json#', $header)) {
                $isApi = true;
                break;
            }
        }

        if ($isApi) {
            return $requestHandler->handle($request);
        }

        return new Response('302', ['location' => '/']);
    }
}