<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\WebSockets\Messages;

use BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use Illuminate\Support\Str;
use Ratchet\ConnectionInterface;
use stdClass;

class PusherChannelProtocolMessage implements PusherMessage
{
    public function __construct(
        protected stdClass $payload,
        protected ConnectionInterface $connection,
        protected ChannelManager $channelManager
    ) {
    }

    public function respond()
    {
        $eventName = Str::camel(Str::after($this->payload->event, ':'));

        if (method_exists($this, $eventName) && $eventName !== 'respond') {
            call_user_func([$this, $eventName], $this->connection, $this->payload->data ?? new stdClass());
        }
    }

    /*
     * @link https://pusher.com/docs/pusher_protocol#ping-pong
     */

    public function unsubscribe(ConnectionInterface $connection, stdClass $payload)
    {
        $channel = $this->channelManager->findOrCreate($connection->app->id, $payload->channel);

        $channel->unsubscribe($connection);
    }

    /*
     * @link https://pusher.com/docs/pusher_protocol#pusher-subscribe
     */

    protected function ping(ConnectionInterface $connection)
    {
        $connection->send(json_encode([
            'event' => 'pusher:pong',
        ]));
    }

    protected function subscribe(ConnectionInterface $connection, stdClass $payload)
    {
        $channel = $this->channelManager->findOrCreate($connection->app->id, $payload->channel);

        $channel->subscribe($connection, $payload);
    }
}
