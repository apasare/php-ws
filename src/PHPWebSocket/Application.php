<?php

namespace PHPWebSocket;

use PHPWebSocket\Server;
use PHPWebSocket\Logger;

class Application
{
    static protected $logger;

    static public function getLogger()
    {
        if (is_null(static::$logger)) {
            static::$logger = new Logger();
        }

        return static::$logger;
    }

    static public function start()
    {
        // run server
        $server = new Server(8000);
        $server->setLogger(static::getLogger());
        $server->start();
    }
}
