<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\Server\Logger;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

class Logger
{
    /**
 * @var bool
*/
    protected $enabled = false;

    /**
 * @var bool
*/
    protected $verbose = false;

    public function __construct(protected OutputInterface $consoleOutput)
    {
    }

    public static function isEnabled(): bool
    {
        return app(WebsocketsLogger::class)->enabled;
    }

    public function enable($enabled = true)
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function verbose($verbose = false)
    {
        $this->verbose = $verbose;

        return $this;
    }

    protected function info(string $message)
    {
        $this->line($message, 'info');
    }

    protected function line(string $message, string $style)
    {
        $styled = $style ? "<$style>$message</$style>" : $message;

        $this->consoleOutput->writeln($styled);
    }

    protected function warn(string $message)
    {
        if (!$this->consoleOutput->getFormatter()->hasStyle('warning')) {
            $style = new OutputFormatterStyle('yellow');

            $this->consoleOutput->getFormatter()->setStyle('warning', $style);
        }

        $this->line($message, 'warning');
    }

    protected function error(string $message)
    {
        $this->line($message, 'error');
    }
}
