<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\WebSockets\Messages;

use BeyondCode\LaravelWebSockets\Console\Tcp;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MessageProvider
 *
 * @package Service\Socket\Messages
 */
class MessageProvider
{
    /**
     * MessageProvider constructor.
     *
     * @param string $routeFile
     * @param array $routeData
     * @param Model $user
     * @param object $data
     */
    public function __construct(
        protected string $routeFile,
        protected array $routeData,
        protected Model $user,
        protected object $data
    )
    {
    }

    /**
     *
     */
    public function routeProvider(): void
    {
        $routes = require base_path("routes/socket/$this->routeFile.php");
        $class = array_keys($routes[$this->routeData['url']])[0];

        if (!class_exists($class)) {
            return;
        }

        Tcp::dispatch(
            $this->user,
            $this->data,
            $class,
            $routes,
            $this->routeData
        )->onQueue(config('websockets.channels.queue_handler'));
    }
}
