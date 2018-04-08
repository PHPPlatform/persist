<?php

namespace PhpPlatform\Persist\Exception;

use PhpPlatform\Errors\Exceptions\Persistence\PersistenceException;

class TransactionInitError extends PersistenceException{
    
    public function __construct($message = "",$previous = null){
        parent::__construct($message,11004,$previous);
    }
    
}