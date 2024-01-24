<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\WebSockets\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JsonException;
use Ratchet\RFC6455\Messaging\MessageInterface;

class Received
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The decoded message as array.
     *
     * @var array
     */
    public mixed $decodedMessage;

    /**
     * Create a new event instance.
     *
     * @param string $appId
     * @param string $socketId
     * @param MessageInterface $message
     * @return void
     * @throws JsonException
     */
    public function __construct(string $appId, string $socketId, MessageInterface $message)
    {
        $this->decodedMessage = json_decode($message->getPayload(), true, 512, JSON_THROW_ON_ERROR);
    }
}
