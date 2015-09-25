<?php

class CurlException extends \Exception
{
    /**
     * @param resource $ch Resource of Curl
     */
    public function __construct($ch) {
        parent::__construct(curl_error($ch), curl_errno($ch));
    }
}