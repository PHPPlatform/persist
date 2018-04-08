<?php

namespace PhpPlatform\Persist\Transaction;

use PhpPlatform\Persist\Connection\ConnectionFactory;
use PhpPlatform\Persist\Exception\TransactionInitError;
use PhpPlatform\Persist\Connection\Connection;
use PhpPlatform\Persist\Exception\TransactionNotActiveException;

class Transaction {
    
    /**
     * @var Connection|null
     */
    private $connection = null;
    
    /**
     * @var Transaction
     */
    private $parentTransaction = null;
    
    /**
     * This is the latest timeZone set in the parentTransaction , before starting this sub Transaction
     * @var string
     */
    private $lastTimeZoneInParentTransaction = null;
    
    /**
     * @var boolean
     */
    private $superUser = null;
    
    /**
     * @var array
     */
    private $attributes = null;
    
    /**
     * @var Transaction
     */
    protected static $transaction = null;
    
    /**
     * @param string $connectionName name of the connection to use in this transaction , connection parameters are configured in config.json
     * @param Transaction $parent 
     * @param boolean $superUser
     */
    protected function __construct($connectionName = null,$superUser = false){
        try{
            $connection = ConnectionFactory::getConnection($connectionName);
            $this->connection = $connection;
            $this->parentTransaction = self::$transaction;
            self::$transaction = $this;
            $this->superUser = $superUser;
            if($this->parentTransaction && $this->parentTransaction->superUser){
                // if parent transaction is in superUser , then this transaction will also be with superUser
                $this->superUser = true;
            }
            
            $this->connection->autocommit(false);
            $this->connection->startTransaction();
            
            if($this->parentTransaction == null){
                // this is the new Transaction, so sync the timezone between php and database
                $this->connection->setTimeZone();
            }else{
                $this->lastTimeZoneInParentTransaction = $this->parentTransaction->connection->getTimeZone();
            }
        }catch (\Exception $e){
            throw new TransactionInitError($e->getMessage(),$e);
        }
    }
    
    protected function abort(){
        // rollback current transaction
        if(!($this->connection instanceof Connection)){
            throw new TransactionNotActiveException('Trying to abort an invalid transaction');
        }
        $this->connection->abortTransaction();
        $this->resetThisTransactionInstance();
    }
    
    protected function commit(){
        if(!($this->connection instanceof Connection)){
            throw new TransactionNotActiveException('Trying to commit an invalid transaction');
        }
        $this->connection->commitTransaction();
        $this->resetThisTransactionInstance();
    }
    
    function getConnection(){
        return $this->connection;
    }
    
    function isSuperUser(){
        return $this->superUser;
    }
    
    function getAttribute($name){
        return $this->attributes[$name];
    }
    
    function setAttribute($name,$value){
        $this->attributes[$name] = $value;
        return $this;
    }
    
    /**
     * This method does opposite of construct, resets all instance variables to null thus making this instance unusable
     */
    private function resetThisTransactionInstance(){
        $this->connection = null;
        self::$transaction = $this->parentTransaction;
        // resync the timezone to the one that is set in parent Transaction 
        if(isset(self::$transaction)){
            self::$transaction->getConnection()->setTimeZone($this->lastTimeZoneInParentTransaction);
        }
        $this->lastTimeZoneInParentTransaction = null;
        $this->parentTransaction = null;
        $this->superUser = null;
        $this->attributes = null;
    }
    
}