<?php

namespace Cherry\Middleware;

use Cherry\Session\SessionInterface;
use GuzzleHttp\Cookie\SetCookie;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;

class SessionCookieMiddleware implements MiddlewareInterface
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
        $response = $requestHandler->handle($request);
        if (!$this->container->has('session')) {
            return $response;
        }

        $session = $this->container->get('session');
        if ($session->isStarted()) {
            if (!$this->requestHasSessionId($request, $session)) {
                $cookie = new SetCookie([
                    'Name' => $session->getName(),
                    'Value' => $session->getId(),
                    'Expires' => time() + 3600 * 24 * 365,
                    'HttpOnly' => true,
                ]);
                $response = $response->withHeader('Set-Cookie', (string)$cookie);
            }
            $session->commit();
        }
        return $response;
    }

    protected function requestHasSessionId(ServerRequestInterface $request, SessionInterface $session)
    {
        $cookies = $request->getCookieParams();
        foreach ($cookies as $k => $v) {
            if ($k === $session->getName()) {
                return true;
            }
        }
        return false;
    }
}