<?php
/**
 * User: Raaghu
 * Date: 8/7/13
 * Time: 2:38 PM
 */
namespace PhpPlatform\Persist;

use PhpPlatform\Persist\Exception\TransactionNotActiveException;
use PhpPlatform\Persist\Connection\Connection;
use PhpPlatform\Persist\Transaction\TransactionManager as NewTransactionManager;
use PhpPlatform\Persist\Transaction\Transaction;

/**
 * @deprecated please use PhpPlatform\Persist\Transaction\TransactionManager instead
 * 
 * keeping this class for backword compatibility
 *
 */
class TransactionManager {
    
    /**
     * @var Transaction[]
     */
    private static $transactionStack = [];

    static function startTransaction($conection = null,$superUser = null,$timezone = null){
        if(isset($conection) || isset($timezone)){
            die("Wrong way of using TransactionManager");
        }
        self::$transactionStack[] = NewTransactionManager::startTransaction(null,$superUser);
    }

    static function commitTransaction(){
        $transaction = array_pop(self::$transactionStack);
        NewTransactionManager::commitTransaction($transaction);
    }

    static function abortTransaction(){
        $transaction = array_pop(self::$transactionStack);
        NewTransactionManager::abortTransaction($transaction);
    }

    /**
     * @throws TransactionNotActiveException
     * @return Connection
     */
    static function getConnection(){
        if(count(self::$transactionStack) == 0){
            return null;
        }
        return self::$transactionStack[count(self::$transactionStack)-1]->getConnection();
    }

    /**
     * @return boolean
     */
    static function isSuperUser(){
        if(count(self::$transactionStack) == 0){
            return null;
        }
        return self::$transactionStack[count(self::$transactionStack)-1]->isSuperUser();
    }
    
    static function getAttribute($name){
        if(count(self::$transactionStack) == 0){
            return null;
        }
        return self::$transactionStack[count(self::$transactionStack)-1]->getAttribute($name);
    }
    
    static function setAttribute($name,$value){
        if(count(self::$transactionStack) == 0){
            return null;
        }
        return self::$transactionStack[count(self::$transactionStack)-1]->setAttribute($name,$value);
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
    static function executeInTransaction($callback, $parameters = array(), $superUser = null,$connection = null,$timeZone = null){
        try{
            self::startTransaction($connection,$superUser,$timeZone);
            call_user_func($callback,$parameters);
            self::commitTransaction();
        }catch (\Exception $e){
            self::abortTransaction();
            throw $e;
        }
    }
}