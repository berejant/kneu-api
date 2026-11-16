<?php

declare(strict_types=1);

namespace Kneu\Tests;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * What a PSR-18 client throws when it gets no response
 */
final class NetworkException extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(private readonly RequestInterface $request, string $message, int $code = 0)
    {
        parent::__construct($message, $code);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
