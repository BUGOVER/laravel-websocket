<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\WebSockets\Messages;

interface PusherMessage
{
    public function respond();
}
