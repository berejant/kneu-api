<?php

declare(strict_types=1);

namespace Kneu\Tests;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client of the tests: records the requests and answers each one with the next queued response or exception.
 */
final class MockHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|ClientExceptionInterface> */
    private array $queue = [];

    public function willReturn(ResponseInterface|ClientExceptionInterface ...$responses): self
    {
        array_push($this->queue, ...$responses);

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException('No response queued for ' . $request->getMethod() . ' ' . $request->getUri());
        }

        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }

        return $next;
    }

    /**
     * The URL of each request sent so far
     *
     * @return list<string>
     */
    public function urls(): array
    {
        return array_map(static fn(RequestInterface $request): string => (string) $request->getUri(), $this->requests);
    }
}
