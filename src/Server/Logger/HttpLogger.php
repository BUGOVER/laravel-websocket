<?php

namespace BeyondCode\LaravelWebSockets\Server\Logger;

use Exception;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Ratchet\MessageComponentInterface;

class HttpLogger extends Logger implements MessageComponentInterface
{
    /** @var HttpServerInterface */
    protected $app;

    public static function decorate(MessageComponentInterface $app): self
    {
        $logger = app(self::class);

        return $logger->setApp($app);
    }

    public function setApp(MessageComponentInterface $app)
    {
        $this->app = $app;

        return $this;
    }

    public function onOpen(ConnectionInterface $connection)
    {
        $this->app->onOpen($connection);
    }

    public function onMessage(ConnectionInterface $connection, $message)
    {
        $this->app->onMessage($connection, $message);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->app->onClose($conn);
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        $exceptionClass = get_class($e);

        $message = "Exception `{$exceptionClass}` thrown: `{$e->getMessage()}`";

        if ($this->verbose) {
            $message .= $e->getTraceAsString();
        }

        $this->error($message);

        $this->app->onError($conn, $e);
    }
}
