<?php

namespace Diarmuidie\ImageRack\Exceptions;

class NotFound extends \Exception
{
    public function __construct($message = null, $code = 0, Exception $previous = null)
    {
        http_response_code(404);

        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }
}
