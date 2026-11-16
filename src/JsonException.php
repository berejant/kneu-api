<?php

declare(strict_types=1);

namespace Kneu;

/**
 * Тіло відповіді 2xx не є очікуваним JSON: його не вдалося розібрати (код — константа JSON_ERROR_*, getPrevious() —
 * \JsonException), або це не об'єкт і не масив. Тіло — getResponse().
 *
 * @package kneu/api
 */
class JsonException extends TransportException
{
    public function __construct(string $response, string $error = 'Syntax error', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct('Response JSON: ' . $error, $code, $previous, $response);
    }
}
