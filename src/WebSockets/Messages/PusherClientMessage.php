<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\WebSockets\Messages;

use BeyondCode\LaravelWebSockets\Dashboard\DashboardLogger;
use BeyondCode\LaravelWebSockets\WebSockets\Channels\Channel;
use BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use JsonException;
use Ratchet\ConnectionInterface;
use stdClass;

class PusherClientMessage implements PusherMessage
{
    public function __construct(
        protected stdClass $payload,
        protected ConnectionInterface $connection,
        protected ChannelManager $channelManager
    )
    {
    }

    /**
     * @throws JsonException
     */
    public function respond()
    {
        if (!Str::startsWith($this->payload->event, 'client-')) {
            return;
        }

        if (!$this->connection->app->clientMessagesEnabled) {
            return;
        }

        DashboardLogger::clientMessage($this->connection, $this->payload);

        /* @var Channel|null $channel */
        $channel = $this->channelManager->find($this->connection->app->id, $this->payload->channel);

        if (!json_encode($channel, JSON_THROW_ON_ERROR)) {
            return;
        }

        $this->channelDescriptor($channel);
    }

    /**
     * @param Channel|null $channel
     * @return void
     */
    protected function channelDescriptor(Channel|null $channel): void
    {
        $channels = config('websockets.channels');

        foreach ($channels['names'] as $name => $route) {
            if ($this->isChannelStarts("presence-$name") || $this->isChannelStarts("private-$name")) {
                $this->channelManager('client-' . $channels['prefix'], $route);
                break;
            }
        }

        optional($channel)->broadcastToOthers($this->connection, $this->payload);
    }

    /**
     * @param $channel
     * @return bool
     */
    protected function isChannelStarts($channel): bool
    {
        return Str::startsWith($this->payload->channel, $channel);
    }

    /**
     * @param string $prefix
     * @param string $route_file
     */
    public function channelManager(string $prefix, string $route_file): void
    {
        $route_data = $this->hasRoute($prefix, $route_file);

        if (!$route_data) {
            return;
        }

        $model_name = $this->getModelByGuard($route_data['method']['guard']);
        $channel_name = explode('.', Str::after($this->payload->channel, '.'));
        $model_auth = (new $model_name())->socketAuth;

        if ($model_name && count($channel_name) !== count($model_auth)) {
            return;
        }

        $where = [];

        foreach ($model_auth as $key => $auth) {
            $where[] = [$auth, '=', $channel_name[$key]];
        }

        $eloquent_where = [];

        foreach ($where as $value) {
            $eloquent_where[] = "$value[0],$value[1],$value[2]";
        }

        $data = explode(',', $eloquent_where[0]);

        $user = (new $model_name())
            ->where((string) $data[0], $data[1], $data[2])
            ->where(
                static function ($query) use ($eloquent_where) {
                    unset($eloquent_where[0]);
                    foreach ($eloquent_where as $where) {
                        $data = explode(',', $where);
                        $query->where((string) $data[0], $data[1], $data[2]);
                    }
                }
            )
            ->first($model_auth);

        if ($user) {
            (new MessageProvider($route_file, $route_data, $user, $this->payload->data))->routeProvider();
        }
    }

    /**
     * @param $prefix
     * @param $route_file
     * @return array|null
     */
    public function hasRoute($prefix, $route_file): ?array
    {
        $url = str_replace(['/', '\\', ' '], '', Str::after($this->payload->event, $prefix));
        $routes = require base_path("routes/socket/$route_file.php");

        if (!\array_key_exists($url, $routes)) {
            return null;
        }

        return ['url' => $url, 'method' => $routes[$url]];
    }

    /**
     * @param $guard
     * @return Model
     */
    protected function getModelByGuard($guard): string
    {
        $guard = config('auth.guards.' . $guard);

        return config('auth.providers.' . $guard['provider'] . '.model');
    }
}
