<?php

declare(strict_types=1);

namespace Kneu;

/**
 * Сервер відповів HTTP-кодом поза 2xx без JSON-помилки: код — getCode() (getStatusCode()), тіло — getResponse().
 *
 * @package kneu/api
 */
class HttpException extends TransportException
{
    public function __construct(int $statusCode, ?string $response)
    {
        parent::__construct('Waiting for 20x HTTP code, but receiving ' . $statusCode, $statusCode, null, $response);
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }
}
