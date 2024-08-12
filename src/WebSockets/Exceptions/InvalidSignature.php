<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\WebSockets\Exceptions;

class InvalidSignature extends WebSocketException
{
    public function __construct()
    {
        $this->message = 'Invalid Signature';

        $this->code = 4009;
    }
}
