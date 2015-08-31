<?php

namespace PHPWebSocket;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class Logger implements LoggerInterface
{
    use LoggerTrait;

    const MESSAGE_TYPE_SYS = 0;
    const MESSAGE_TYPE_MAIL = 1;
    const MESSAGE_TYPE_FILE = 3;
    const MESSAGE_TYPE_SAPI = 4;

    public $messageType;
    public $destination;
    public $extraHeaders;

    public function __construct($messageType = 0, $destination = '', $extraHeaders = '')
    {
        $this->messageType = $messageType;
        $this->destination = $destination;
        $this->extraHeaders = $extraHeaders;
    }

    /**
    * Interpolates context values into the message placeholders.
    */
    function interpolate($message, array $context = array())
    {
        // build a replacement array with braces around the context keys
        $replace = array();
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $val;
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

    public function log($level, $message, array $context = array())
    {
        $message = date('Y-m-d H:i:s') . ' - ' . strtoupper($level) . ' - ' . $this->interpolate($message, $context);

        error_log($message, $this->messageType, $this->destination, $this->extraHeaders);
    }
}