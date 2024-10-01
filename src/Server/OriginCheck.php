<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\Server;

use Exception;
use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\CloseResponseTrait;
use Ratchet\Http\HttpServerInterface;
use Ratchet\MessageComponentInterface;

class OriginCheck implements HttpServerInterface
{
    use CloseResponseTrait;

    /** @var MessageComponentInterface */
    protected $_component;

    protected $allowedOrigins = [];

    public function __construct(MessageComponentInterface $component, array $allowedOrigins = [])
    {
        $this->_component = $component;

        $this->allowedOrigins = $allowedOrigins;
    }

    public function onOpen(ConnectionInterface $connection, RequestInterface $request = null)
    {
        if ($request->hasHeader('Origin')) {
            $this->verifyOrigin($connection, $request);
        }

        return $this->_component->onOpen($connection, $request);
    }

    protected function verifyOrigin(ConnectionInterface $connection, RequestInterface $request)
    {
        $header = (string)$request->getHeader('Origin')[0];
        $origin = parse_url($header, PHP_URL_HOST) ?: $header;

        if (!empty($this->allowedOrigins) && !in_array($origin, $this->allowedOrigins)) {
            return $this->close($connection, 403);
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        return $this->_component->onMessage($from, $msg);
    }

    public function onClose(ConnectionInterface $conn)
    {
        return $this->_component->onClose($conn);
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        return $this->_component->onError($conn, $e);
    }
}
