<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\Statistics\Logger;

use BeyondCode\LaravelWebSockets\Apps\App;
use BeyondCode\LaravelWebSockets\Statistics\Http\Controllers\WebSocketStatisticsEntriesController;
use BeyondCode\LaravelWebSockets\Statistics\Statistic;
use BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use GuzzleHttp\Psr7\Utils;
use Ratchet\ConnectionInterface;
use React\Http\Browser;

class HttpStatisticsLogger implements StatisticsLogger
{
    /**
 * @var Statistic[]
*/
    protected $statistics = [];

    /**
 * @var ChannelManager
*/
    protected $channelManager;

    /**
 * @var Browser
*/
    protected $browser;

    public function __construct(ChannelManager $channelManager, Browser $browser)
    {
        $this->channelManager = $channelManager;

        $this->browser = $browser;
    }

    public function webSocketMessage(ConnectionInterface $connection)
    {
        $this
            ->findOrMakeStatisticForAppId($connection->app->id)
            ->webSocketMessage();
    }

    protected function findOrMakeStatisticForAppId($appId): Statistic
    {
        if (!isset($this->statistics[$appId])) {
            $this->statistics[$appId] = new Statistic($appId);
        }

        return $this->statistics[$appId];
    }

    public function apiMessage($appId)
    {
        $this
            ->findOrMakeStatisticForAppId($appId)
            ->apiMessage();
    }

    public function connection(ConnectionInterface $connection)
    {
        $this
            ->findOrMakeStatisticForAppId($connection->app->id)
            ->connection();
    }

    public function disconnection(ConnectionInterface $connection)
    {
        $this
            ->findOrMakeStatisticForAppId($connection->app->id)
            ->disconnection();
    }

    public function save()
    {
        foreach ($this->statistics as $appId => $statistic) {
            if (!$statistic->isEnabled()) {
                continue;
            }

            $postData = array_merge($statistic->toArray(), [
                'secret' => App::findById($appId)->secret,
            ]);

            $this
                ->browser
                ->post(
                    action([WebSocketStatisticsEntriesController::class, 'store']),
                    ['Content-Type' => 'application/json'],
                    Utils::streamFor(json_encode($postData))
                );

            $currentConnectionCount = $this->channelManager->getConnectionCount($appId);
            $statistic->reset($currentConnectionCount);
        }
    }
}
