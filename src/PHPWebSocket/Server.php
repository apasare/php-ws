<?php

namespace PHPWebSocket;

use PHPWebSocket\Application;
use PHPWebSocket\ClientsWorker;
use Psr\Log\LoggerAwareTrait;

class Server
{
    use LoggerAwareTrait;

    protected $_port;
    protected $_address;
    protected $_socket;
    protected $_errno;
    protected $_errstr;

    protected $_clientsWorkers;
    protected $_clients = [];
    protected $_workers = [];
    protected $_conds = [];
    protected $_mutexs = [];

    public function __construct($port, $address='0.0.0.0', $clientsWorkers=5)
    {
        $this->_port = $port;
        $this->_address = $address;
        $this->_clientsWorkers = $clientsWorkers;
    }

    public function __destruct()
    {
        if (is_resource($this->_socket)) {
            fclose($this->_socket);
        }
    }

    public function createSocket()
    {
        if (!is_resource($this->_socket)) {
            $this->logger->debug('creating the nest on {address}:{port}', array(
                'address' => $this->_address,
                'port' => $this->_port
            ));

            $localSocket = "tcp://{$this->_address}:{$this->_port}";
            $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
            $context = stream_context_create();
            $this->_socket = stream_socket_server($localSocket, $this->_errno, $this->_errstr, $flags, $context);
        }
    }

    public function initWorkers()
    {
        if (is_resource($this->_socket)) {
            $this->logger->debug('{dragons} dragons incoming, Khaleesi!', array(
                'dragons' => $this->_clientsWorkers
            ));

            for ($i=0; $i<$this->_clientsWorkers; ++$i) {
                $stackedClients = new \Stackable();
                $cond = \Cond::create();
                $mutex = \Mutex::create();

                $this->_conds[] = $cond;
                $this->_mutexs[] = $mutex;
                $this->_clients[$i] = $stackedClients;
                $this->_workers[$i] = new ClientsWorker($i, $stackedClients, $cond, $mutex, $this->logger);
            }
        }
    }

    public function listen()
    {
        if (is_resource($this->_socket)) {
            $i = 0;
            while (true) {
                $client = stream_socket_accept($this->_socket, -1);

                if (is_resource($client)) {
                    stream_set_blocking($client, 0);
                    $this->_clients[$i][] = $client;
                    \Cond::signal($this->_conds[$i]);

                    if (++$i % $this->_clientsWorkers == 0) {
                        $i = 0;
                    }
                }
            }

            socket_close($this->_socket);
        }
    }

    public function start()
    {
        $this->createSocket();
        if ($this->_socket) {
            $this->initWorkers();
            $this->listen();
        }
    }
}
