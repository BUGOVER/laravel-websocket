<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\Console;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Class Tcp
 *
 * @package Src\Jobs
 */
class Tcp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Tcp constructor.
     *
     * @param object $user
     * @param object $data
     * @param string $class
     * @param array $routes
     * @param array $routeData
     */
    public function __construct(
        protected object $user,
        protected object $data,
        protected string $class,
        protected array $routes,
        protected array $routeData
    )
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $broadcast = resolve($this->class);
        $method = array_values($this->routes[$this->routeData['url']])[0];

        if (!method_exists($broadcast, $method)) {
            return;
        }

        $request = (new Request())->replace(['user' => $this->user, 'data' => (array) $this->data]);
        $broadcast->{$method}($request);
    }
}
