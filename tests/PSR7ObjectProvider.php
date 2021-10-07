<?php

namespace Cherry\Test;

use Http\Factory\Guzzle\ServerRequestFactory;

class PSR7ObjectProvider
{
    public function createServerRequest(string $uri, string $method = 'GET', array $data = [])
    {
        $headers = [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'HTTP_HOST' => 'localhost',
            'HTTP_USER_AGENT' => 'Cherry',
            'BYPASS_CLI_RUNNER' => 1,

        ];
        $serverParams = [
            'QUERY_STRING' => '',
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_METHOD' => $method,
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'REQUEST_URI' => '',
            'SCRIPT_NAME' => '/index.php',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'SERVER_PROTOCOL' => 'HTTP/1.1',
        ];

        $request = $this->getServerRequestFactory()->createServerRequest($method, $uri, $serverParams);
        foreach ($headers as $k => $v) {
            $request = $request->withHeader($k, $v);
        }
        $request = $request->withParsedBody($data);
        return $request;
    }

    public function getServerRequestFactory()
    {
        return new ServerRequestFactory();
    }
}