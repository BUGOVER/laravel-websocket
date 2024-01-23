<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\WebSockets;

use BeyondCode\LaravelWebSockets\Apps\App;
use BeyondCode\LaravelWebSockets\Dashboard\DashboardLogger;
use BeyondCode\LaravelWebSockets\Facades\StatisticsLogger;
use BeyondCode\LaravelWebSockets\QueryParameters;
use BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use BeyondCode\LaravelWebSockets\WebSockets\Exceptions\ConnectionsOverCapacity;
use BeyondCode\LaravelWebSockets\WebSockets\Exceptions\UnknownAppKey;
use BeyondCode\LaravelWebSockets\WebSockets\Exceptions\WebSocketException;
use BeyondCode\LaravelWebSockets\WebSockets\Messages\PusherMessageFactory;
use Exception;
use JsonException;
use Ratchet\ConnectionInterface;
use Ratchet\RFC6455\Messaging\MessageInterface;
use Ratchet\WebSocket\MessageComponentInterface;

//use Random\RandomException;

class WebSocketHandler implements MessageComponentInterface
{
    /** @var ChannelManager */
    protected $channelManager;

    public function __construct(ChannelManager $channelManager)
    {
        $this->channelManager = $channelManager;
    }

    public function onOpen(ConnectionInterface $connection)
    {
        $this
            ->verifyAppKey($connection)
            ->limitConcurrentConnections($connection)
            ->generateSocketId($connection)
            ->establishConnection($connection);
    }

    /**
     * @param ConnectionInterface $connection
     * @return $this
     */
    protected function establishConnection(ConnectionInterface $connection): static
    {
        $connection->send(json_encode([
            'event' => 'pusher:connection_established',
            'data' => json_encode([
                'socket_id' => $connection->socketId,
                'activity_timeout' => 30,
            ]),
        ]));

        DashboardLogger::connection($connection);

        StatisticsLogger::connection($connection);

        return $this;
    }

    /**
     * @param ConnectionInterface $connection
     * @return $this
     * @throws RandomException
     */
    protected function generateSocketId(ConnectionInterface $connection): static
    {
        $socketId = sprintf('%d.%d', random_int(1, 1000000000), random_int(1, 1000000000));

        $connection->socketId = $socketId;

        return $this;
    }

    protected function limitConcurrentConnections(ConnectionInterface $connection)
    {
        if (!is_null($capacity = $connection->app->capacity)) {
            $connectionsCount = $this->channelManager->getConnectionCount($connection->app->id);
            if ($connectionsCount >= $capacity) {
                throw new ConnectionsOverCapacity();
            }
        }

        return $this;
    }

    protected function verifyAppKey(ConnectionInterface $connection): static
    {
        $appKey = QueryParameters::create($connection->httpRequest)->get('appKey');

        if (!$app = App::findByKey($appKey)) {
            throw new UnknownAppKey($appKey);
        }

        $connection->app = $app;

        return $this;
    }

    /**
     * @throws JsonException
     */
    public function onMessage(ConnectionInterface $conn, MessageInterface $msg): void
    {
        $msg = PusherMessageFactory::createForMessage($msg, $conn, $this->channelManager);

        $msg->respond();

        StatisticsLogger::webSocketMessage($conn);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->channelManager->removeFromAllChannels($conn);

        DashboardLogger::disconnection($conn);

        StatisticsLogger::disconnection($conn);
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        if ($e instanceof WebSocketException) {
            $conn->send(
                json_encode(
                    $e->getPayload()
                )
            );
        }
    }
}
