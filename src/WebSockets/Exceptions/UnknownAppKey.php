<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\WebSockets\Exceptions;

class UnknownAppKey extends WebSocketException
{
    public function __construct(string $appKey)
    {
        $this->message = "Could not find app key `{$appKey}`.";

        $this->code = 4001;
    }
}
