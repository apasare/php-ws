<?php

namespace PHPWebSocket;

use Cond;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class ClientsWorker extends \Worker
{
    use LoggerAwareTrait;

    public function __construct($index, $clients, $cond, $mutex, LoggerInterface $logger)
    {
        $this->index = $index;
        $this->clients = $clients;
        $this->cond = $cond;
        $this->mutex = $mutex;

        $this->setLogger($logger);

        $this->start();
    }

    protected function logDebug($message, array $context = array())
    {
        $this->logger->debug($message, $context);
    }

    public function fetchNewClients(&$clients)
    {
        while ($client = $this->clients->shift()) {
            if (is_resource($client)) {
                $clients[(string)$client] = $client;
            }
        }
    }

    public function closeClientSocket(&$clients, $client)
    {
        $key = (string) $client;
        if (isset($clients[$key])) {
            $this->logDebug('nothing left; dragon {thread_id} is sad and leaves {client}', array(
                'client' => $client,
                'thread_id' => $this->getThreadId()
            ));

            stream_socket_shutdown($client, STREAM_SHUT_RDWR);
            unset($clients[$key]);
        }
    }

    public function readFromClient($client, $chunkSize = 1024)
    {
        $input = '';
        while ($buffer = fread($client, $chunkSize)) {
            $input .= $buffer;
        }
        $totalBytesRead = strlen($input);

        $this->logDebug('dragon {thread_id} ate {bytes_read} sheeps from {client}', array(
            'bytes_read' => $totalBytesRead,
            'client' => $client,
            'thread_id' => $this->getThreadId()
        ));

        return [$totalBytesRead, $input];
    }

    public function writeToClient($client, $string)
    {
        for ($bytesWritten = 0; $bytesWritten < strlen($string); $bytesWritten += $fwrite) {
            $fwrite = fwrite($client, substr($string, $bytesWritten));
            if ($fwrite === false) {
                return $bytesWritten;
            }
        }

        return $bytesWritten;
    }

    public function doSocketSelect(&$clients)
    {
        if (count($clients)) {
            $read = (array) $clients;
            $write = $except = null;
            $tv_sec = 0;
            $tv_usec = 15000;
            if (stream_select($read, $write, $except, $tv_sec, $tv_usec)) {
                foreach ($read as $client) {
                    list($bytesRead, $input) = $this->readFromClient($client);

                    if ($bytesRead === 0) {
                        $this->closeClientSocket($clients, $client);
                    } elseif ($bytesRead) {
                        $body = 'lorem ipsum dolor sit amet';
                        $headers = array(
                            'HTTP/1.1 200 OK',
                            'Connection: close',
                            'Content-Type: text/html',
                            'Content-Length: ' . strlen($body)
                        );
                        // TODO implement WebSocket logic
                        // @https://tools.ietf.org/html/rfc6455
                        $response = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n";
                        $this->writeToClient($client, $response);
                    }
                }
            }
        } else {
            $this->logDebug('dragon {thread_id} is bored', array('thread_id' => $this->getThreadId()));
            Cond::wait($this->cond, $this->mutex);
            $this->logDebug('dragon {thread_id} is rampaging', array('thread_id' => $this->getThreadId()));
        }
    }

    public function run()
    {
        $clients = [];
        while (true) {
            $this->fetchNewClients($clients);
            $this->doSocketSelect($clients);
        }
    }
}
