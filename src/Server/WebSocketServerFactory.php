<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\Server;

use BeyondCode\LaravelWebSockets\Server\Logger\HttpLogger;
use Ratchet\Http\Router;
use Ratchet\Server\IoServer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SecureServer;
use React\Socket\Server;
use React\Socket\SocketServer;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

class WebSocketServerFactory
{
    /** @var string */
    protected $host = '127.0.0.1';

    /** @var int */
    protected $port = 8080;

    /** @var LoopInterface */
    protected $loop;

    /** @var RouteCollection */
    protected $routes;

    /** @var Symfony\Component\Console\Output\OutputInterface */
    protected $consoleOutput;

    public function __construct()
    {
        $this->loop = Loop::get(); // LoopFactory::create();
    }

    public function useRoutes(RouteCollection $routes): static
    {
        $this->routes = $routes;

        return $this;
    }

    public function setHost(string $host): static
    {
        $this->host = $host;

        return $this;
    }

    public function setPort(string $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function setLoop(LoopInterface $loop): static
    {
        $this->loop = $loop;

        return $this;
    }

    public function setConsoleOutput(OutputInterface $consoleOutput): static
    {
        $this->consoleOutput = $consoleOutput;

        return $this;
    }

    public function createServer(): IoServer
    {
        $socket = new SocketServer(uri: "{$this->host}:{$this->port}", loop: $this->loop); // new Server();

        if (config('websockets.ssl.local_cert')) {
            $socket = new SecureServer($socket, $this->loop, config('websockets.ssl'));
        }

        $urlMatcher = new UrlMatcher($this->routes, new RequestContext());

        $router = new Router($urlMatcher);

        $app = new OriginCheck($router, config('websockets.allowed_origins', []));

        $httpServer = new HttpServer($app, config('websockets.max_request_size_in_kb') * 1024);

        if (HttpLogger::isEnabled()) {
            $httpServer = HttpLogger::decorate($httpServer);
        }

        return new IoServer($httpServer, $socket, $this->loop);
    }
}
