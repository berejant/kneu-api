<?php

declare(strict_types=1);

namespace Kneu;

/**
 * Відповіді немає (CurlException) або вона не така, як очікувалося (HttpException, JsonException).
 *
 * @package kneu/api
 */
abstract class TransportException extends \Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?string $response = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Тіло відповіді сервера, якщо відповідь була
     */
    public function getResponse(): ?string
    {
        return $this->response;
    }
}
