<?php

namespace Cherry\Test\Traits;

use donatj\MockWebServer\MockWebServer;

trait MockServer
{
    /**
     * @var MockWebServer
     */
    public static $server;

    public static function startMockServer()
    {
        self::$server = new MockWebServer(8088);
        self::$server->start();
    }

    public static function stopMockerServer()
    {
        self::$server->stop();
    }
}