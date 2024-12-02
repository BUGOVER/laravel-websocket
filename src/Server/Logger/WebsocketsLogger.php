<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\Server\Logger;

use BeyondCode\LaravelWebSockets\QueryParameters;
use Exception;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Ratchet\RFC6455\Messaging\MessageInterface;
use Ratchet\WebSocket\MessageComponentInterface;

class WebsocketsLogger extends Logger implements MessageComponentInterface
{
    /**
 * @var HttpServerInterface
*/
    protected $app;

    public function onOpen(ConnectionInterface $connection)
    {
        $appKey = QueryParameters::create($connection->httpRequest)->get('appKey');

        $this->warn("New connection opened for app key {$appKey}.");

        $this->app->onOpen(ConnectionLogger::decorate($connection));
    }

    public static function decorate(MessageComponentInterface $app): self
    {
        return app(self::class)->setApp($app);
    }

    public function setApp(MessageComponentInterface $app)
    {
        $this->app = $app;

        return $this;
    }

    public function onMessage(ConnectionInterface $conn, MessageInterface $msg)
    {
        $this->info(
            "{$conn->app->id}: connection id {$conn->socketId} received message: {$msg->getPayload()}."
        );

        $this->app->onMessage(ConnectionLogger::decorate($conn), $msg);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $socketId = $conn->socketId ?? null;

        $this->warn("Connection id {$socketId} closed.");

        $this->app->onClose(ConnectionLogger::decorate($conn));
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        $exceptionClass = get_class($e);

        $appId = $conn->app->id ?? 'Unknown app id';

        $message = "{$appId}: exception `{$exceptionClass}` thrown: `{$e->getMessage()}`.";

        if ($this->verbose) {
            $message .= $e->getTraceAsString();
        }

        $this->error($message);

        $this->app->onError(ConnectionLogger::decorate($conn), $e);
    }
}
