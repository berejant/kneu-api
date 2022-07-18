<?php
/**
 * @package kneu/api
 */

namespace Kneu;

class HttpException extends TransportException
{
    protected $response;

    public function __construct ($code, $response)
    {
        parent::__construct('Waiting for 20x HTTP code, but receiving ' . $code, $code, null, $response);
    }
}
