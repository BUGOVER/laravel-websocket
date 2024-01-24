<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\Manager;

use BeyondCode\LaravelWebSockets\Guzzle;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastManager as BaseBroadcastManager;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Container\BindingResolutionException;
use Psr\Log\LoggerInterface;
use Pusher\Pusher;
use Pusher\PusherException;

/**
 * Register new pusher instance without curl verify
 */
class TlsBroadcastManager extends BaseBroadcastManager
{
    /**
     * Create an instance of the driver.
     *
     * @param array $config
     * @return Broadcaster|PusherBroadcaster
     * @throws BindingResolutionException
     * @throws PusherException
     */
    protected function createPusherDriver(array $config): Broadcaster|PusherBroadcaster
    {
        $pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            config('broadcasting.connections.pusher.options') ?? [],
            new Guzzle() ?? null // Pass guzzle instance with verify false parameter
        );

        if ($config['log'] ?? false) {
            $pusher->setLogger($this->app->make(LoggerInterface::class));
        }

        return new PusherBroadcaster($pusher);
    }
}
