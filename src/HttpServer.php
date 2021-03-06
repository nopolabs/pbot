<?php

namespace PBot;


use React\EventLoop\LoopInterface;
use React\Http\Server;
use React\Socket\Server as SocketServer;

class HttpServer
{
    private $socket;
    private $config;

    public function __construct(array $config, LoopInterface $loop)
    {
        $this->config = $config;
        $this->socket = new SocketServer($loop);
        $this->http = new Server($this->socket);
    }

    public function init($app, $httpError, $socketError)
    {
        $this->http->on('request', $app);
        $this->http->on('error', $httpError);
        $this->socket->on('error', $socketError);

        $port = $this->config['port'];
        $this->socket->listen($port);
        echo "HttpServer listening on port $port\n";
    }
}