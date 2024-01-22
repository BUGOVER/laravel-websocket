<?php

namespace BeyondCode\LaravelWebSockets\WebSockets\Messages;

use BeyondCode\LaravelWebSockets\Dashboard\DashboardLogger;
use BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use Illuminate\Support\Str;
use Ratchet\ConnectionInterface;
use stdClass;

class PusherClientMessage implements PusherMessage
{
    /** \stdClass */
    protected $payload;

    /** @var ConnectionInterface */
    protected $connection;

    /** @var ChannelManager */
    protected $channelManager;

    public function __construct(stdClass $payload, ConnectionInterface $connection, ChannelManager $channelManager)
    {
        $this->payload = $payload;

        $this->connection = $connection;

        $this->channelManager = $channelManager;
    }

    public function respond()
    {
        if (!Str::startsWith($this->payload->event, 'client-')) {
            return;
        }

        if (!$this->connection->app->clientMessagesEnabled) {
            return;
        }

        DashboardLogger::clientMessage($this->connection, $this->payload);

        $channel = $this->channelManager->find($this->connection->app->id, $this->payload->channel);

        optional($channel)->broadcastToOthers($this->connection, $this->payload);
    }
}
