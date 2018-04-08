<?php

namespace PhpPlatform\Persist\Transaction;

class TransactionManager extends Transaction{
    
    /**
     * @param string $conectionName
     * @param boolean $superUser
     * 
     * @return Transaction
     */
    static function startTransaction($conectionName = null,$superUser = null){
        new Transaction($conectionName,$superUser);
        return self::$transaction;
    }
    
    /**
     * @param Transaction $transaction
     */
    static function commitTransaction($transaction){
        $transaction->commit();
        unset($transaction);
    }
    
    /**
     * @param Transaction $transaction
     */
    static function abortTransaction($transaction){
        if(isset($transaction)){
            $transaction->abort();
            unset($transaction);
        }
    }
    
    /**
     *
     * @param callable $callback
     * @param array $parameters
     * @param boolean $superUser
     * @param Connection $connection
     * @param string $timeZone
     * @throws Exception
     */
    static function executeInTransaction($callback, $superUser = null,$connectionName = null){
        $transaction = null;
        try{
            $transaction = self::startTransaction($connectionName, $superUser);
            
            call_user_func($callback);
            
            self::commitTransaction($transaction);
        }catch (\Exception $e){
            self::abortTransaction($transaction);
            throw $e;
        }
    }
    
}