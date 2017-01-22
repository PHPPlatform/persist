<?php

/**
 * User: Raaghu
 * Date: 27-08-2015
 * Time: PM 09:26
 */

namespace PhpPlatform\Persist\Exception;

use PhpPlatform\Errors\Exceptions\Persistence\PersistenceException;

class ObjectStateException extends PersistenceException{

    public function __construct($message = "",$previous = null){
        parent::__construct($message,11001,$previous);
    }

}
