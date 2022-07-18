<?php
/**
 * @package kneu/api
 */

namespace Kneu;

abstract class TransportException extends \Exception
{
    /** @var ?string */
    private $response;

    public function __construct($message = "", $code = 0, $previous = null, $response = null)
    {
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }

    public function getResponse()
    {
        return $this->response;
    }

}
