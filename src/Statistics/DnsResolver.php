<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\Statistics;

use React\Dns\Resolver\ResolverInterface;
use React\Promise;

class DnsResolver implements ResolverInterface
{
    private $internalIP = '127.0.0.1';

    /*
     * This empty constructor is needed so we don't have to setup the parent's dependencies.
     */
    public function __construct()
    {
        //
    }

    public function resolve($domain)
    {
        return $this->resolveInternal($domain);
    }

    private function resolveInternal($domain, $type = null)
    {
        return new Promise\resolve($this->internalIP);
    }

    public function resolveAll($domain, $type)
    {
        return $this->resolveInternal($domain, $type);
    }

    public function __toString()
    {
        return $this->internalIP;
    }
}
