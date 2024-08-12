<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\WebSockets\Exceptions;

use Exception;

class WebSocketException extends Exception
{
    public function getPayload()
    {
        return [
            'event' => 'pusher:error',
            'data' => [
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
            ],
        ];
    }
}
