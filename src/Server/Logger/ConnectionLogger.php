<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\Server\Logger;

use Ratchet\ConnectionInterface;

class ConnectionLogger extends Logger implements ConnectionInterface
{
    /**
 * @var ConnectionInterface
*/
    protected $connection;

    /**
     * @param ConnectionInterface $app
     * @return self
     */
    public static function decorate(ConnectionInterface $app): self
    {
        return app(self::class)->setConnection($app);
    }

    public function send($data)
    {
        $socketId = $this->connection->socketId ?? null;

        $this->info("Connection id {$socketId} sending message {$data}");

        $this->connection->send($data);
    }

    public function close()
    {
        $this->warn("Connection id {$this->connection->socketId} closing.");

        $this->connection->close();
    }

    public function __get($name)
    {
        return $this->connection->$name;
    }

    public function __set($name, $value)
    {
        return $this->connection->$name = $value;
    }

    public function __isset($name)
    {
        return isset($this->connection->$name);
    }

    public function __unset($name)
    {
        unset($this->connection->$name);
    }

    protected function getConnection()
    {
        return $this->connection;
    }

    public function setConnection(ConnectionInterface $connection)
    {
        $this->connection = $connection;

        return $this;
    }
}
