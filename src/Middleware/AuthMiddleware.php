<?php

namespace Cherry\Middleware;

use Cherry\Helper\SignRequest;
use Cherry\Session\SessionInterface;
use GuzzleHttp\Cookie\SetCookie;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use GuzzleHttp\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
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
        $session = $this->session($request);
        if (!$session->isStarted() || !$session['is_admin']) {
            return new Response('401');
        }
        return $requestHandler->handle($request);
    }

    protected function session(ServerRequestInterface $request)
    {
        if ($this->container->has('session')) {
            $session = $this->container->get('session');
        } else {
            $factory = $this->container->get(SessionInterface::class);
            $session = $factory($this->container, $request);
        }
        return $session;
    }
}