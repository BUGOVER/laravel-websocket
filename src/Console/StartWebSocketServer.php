<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\Console;

use BeyondCode\LaravelWebSockets\Facades\StatisticsLogger;
use BeyondCode\LaravelWebSockets\Facades\WebSocketsRouter;
use BeyondCode\LaravelWebSockets\Server\Logger\ConnectionLogger;
use BeyondCode\LaravelWebSockets\Server\Logger\HttpLogger;
use BeyondCode\LaravelWebSockets\Server\Logger\WebsocketsLogger;
use BeyondCode\LaravelWebSockets\Server\WebSocketServerFactory;
use BeyondCode\LaravelWebSockets\Statistics\DnsResolver;
use BeyondCode\LaravelWebSockets\Statistics\Logger\HttpStatisticsLogger;
use BeyondCode\LaravelWebSockets\Statistics\Logger\StatisticsLogger as StatisticsLoggerInterface;
use BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use React\Dns\Config\Config as DnsConfig;
use React\Dns\Resolver\Factory as DnsFactory;
use React\Dns\Resolver\ResolverInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Socket\Connector;

class StartWebSocketServer extends Command
{
    protected $signature = 'websockets:serve {--host=0.0.0.0} {--port=6001} {--debug : Forces the loggers to be enabled and thereby overriding the app.debug config setting }';


    protected $description = 'Start the Laravel WebSocket Server';

    /**
     * @var LoopInterface|null
     */
    protected ?LoopInterface $loop;

    /**
     * @var mixed
     */
    protected mixed $lastRestart;

    public function __construct()
    {
        parent::__construct();

        $this->loop = Loop::get();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        $this
            ->configureStatisticsLogger()
            ->configureHttpLogger()
            ->configureMessageLogger()
            ->configureConnectionLogger()
            ->configureRestartTimer()
            ->registerEchoRoutes()
            ->registerCustomRoutes()
            ->startWebSocketServer();
    }

    /**
     * @return void
     */
    protected function startWebSocketServer(): void
    {
        $this->info("Starting the WebSocket server on port {$this->option('port')}...");

        $routes = WebSocketsRouter::getRoutes();

        /* 🛰 Start the server 🛰  */
        (new WebSocketServerFactory())
            ->setLoop($this->loop)
            ->useRoutes($routes)
            ->setHost($this->option('host'))
            ->setPort($this->option('port'))
            ->setConsoleOutput($this->output)
            ->createServer()
            ->run();
    }

    /**
     * @return $this
     */
    protected function registerCustomRoutes(): static
    {
        WebSocketsRouter::customRoutes();

        return $this;
    }

    /**
     * @return $this
     */
    protected function registerEchoRoutes(): static
    {
        WebSocketsRouter::echo();

        return $this;
    }

    /**
     * @return $this
     */
    public function configureRestartTimer(): static
    {
        $this->lastRestart = $this->getLastRestart();

        $this->loop->addPeriodicTimer(10, function () {
            if ($this->getLastRestart() !== $this->lastRestart) {
                $this->loop->stop();
            }
        });

        return $this;
    }

    /**
     * @return mixed
     */
    protected function getLastRestart(): mixed
    {
        return Cache::get('beyondcode:websockets:restart', 0);
    }

    /**
     * @return $this
     */
    protected function configureConnectionLogger(): static
    {
        app()->bind(ConnectionLogger::class, function ($app) {
            return (new ConnectionLogger($this->output))
                ->enable($app['config']['app']['debug'] ?? false)
                ->verbose($this->output->isVerbose());
        });

        return $this;
    }

    /**
     * @return $this
     */
    protected function configureMessageLogger(): static
    {
        app()->singleton(WebsocketsLogger::class, function ($app) {
            return (new WebsocketsLogger($this->output))
                ->enable() // @TODO this debug already true
                ->verbose($this->output->isVerbose());
        });

        return $this;
    }

    /**
     * @return $this
     */
    protected function configureHttpLogger(): static
    {
        app()->singleton(HttpLogger::class, function ($app) {
            return (new HttpLogger($this->output))
                ->enable($this->option('debug') ?: ($app['config']['app']['debug'] ?? false))
                ->verbose($this->output->isVerbose());
        });

        return $this;
    }

    /**
     * @return $this
     */
    protected function configureStatisticsLogger(): static
    {
        $connector = new Connector($this->loop, [
            'dns' => $this->getDnsResolver(),
            'tls' => [
                'verify_peer' => 'production' === config('app.env'),
                'verify_peer_name' => 'production' === config('app.env'),
            ],
        ]);

        $browser = new Browser($this->loop, $connector);

        app()->singleton(StatisticsLoggerInterface::class, function ($app) use ($browser) {
            $config = $app['config']['websockets'];
            $class = $config['statistics']['logger'] ?? HttpStatisticsLogger::class;

            return new $class(app(ChannelManager::class), $browser);
        });

        $this->loop->addPeriodicTimer(config('websockets.statistics.interval_in_seconds'), function () {
            StatisticsLogger::save();
        });

        return $this;
    }

    /**
     * @return ResolverInterface
     */
    protected function getDnsResolver(): ResolverInterface
    {
        if (!config('websockets.statistics.perform_dns_lookup')) {
            return new DnsResolver();
        }

        $dnsConfig = DnsConfig::loadSystemConfigBlocking();

        return (new DnsFactory())->createCached(
            $dnsConfig->nameservers
                ? reset($dnsConfig->nameservers)
                : '1.1.1.1',
            $this->loop
        );
    }
}
