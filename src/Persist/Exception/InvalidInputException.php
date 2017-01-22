<?php

/**
 * User: Raaghu
 * Date: 27-08-2015
 * Time: PM 09:26
 */

namespace PhpPlatform\Persist\Exception;

use PhpPlatform\Errors\Exceptions\Persistence\BadQueryException;

class InvalidInputException extends BadQueryException{

    const ERROR_CODE = 103;

    public function __construct($message = "",$previous = null){
        parent::__construct($message,$previous);
    }

}
