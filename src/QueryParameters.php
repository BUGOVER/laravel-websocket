<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets;

use Psr\Http\Message\RequestInterface;

class QueryParameters
{
    /** @var RequestInterface */
    protected $request;

    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    public static function create(RequestInterface $request)
    {
        return new static($request);
    }

    public function get(string $name): string
    {
        return $this->all()[$name] ?? '';
    }

    public function all(): array
    {
        $queryParameters = [];

        parse_str($this->request->getUri()->getQuery(), $queryParameters);

        return $queryParameters;
    }
}
