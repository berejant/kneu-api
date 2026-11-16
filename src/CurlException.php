<?php

declare(strict_types=1);

namespace Kneu;

use Psr\Http\Client\ClientExceptionInterface;

/**
 * PSR-18 клієнт не отримав відповіді або її тіла (мережа, DNS, TLS, таймаут): причина — getPrevious() (виняток
 * клієнта або помилка читання потоку тіла).
 *
 * Назва лишилася від 1.x, де транспортом був curl. Клас збережено, щоб catch (CurlException) у коді 1.x і далі
 * ловив мережеві помилки: PHP не перевіряє класи в catch, тож без нього такі блоки мовчки перестали б спрацьовувати.
 *
 * @deprecated 2.0 ловіть TransportException; у 3.0 мережеві помилки кидатимуться як TransportException
 * @package kneu/api
 */
class CurlException extends TransportException
{
    public function __construct(ClientExceptionInterface|\RuntimeException $previous)
    {
        parent::__construct($previous->getMessage(), (int) $previous->getCode(), $previous);
    }
}
