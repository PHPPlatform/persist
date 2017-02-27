<?php
/**
 * User: Raaghu
 * Date: 8/7/13
 * Time: 2:38 PM
 */
namespace PhpPlatform\Persist;

use PhpPlatform\Persist\Exception\TransactionNotActiveException;
use PhpPlatform\Persist\Connection\ConnectionFactory;
use PhpPlatform\Persist\Connection\Connection;

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
     * @var boolean
     */
    private $superUser = null;
    
    /**
     * @var array
     */
    private $attributes = null;

    /**
     * @param Connection $connection
     * @param Transaction $parent
     * @param boolean $superUser
     */
    function __construct($connection = null,$parent = null,$superUser = false){
        try{
            if($connection == null){
                $connection = ConnectionFactory::getConnection();
            }
            $this->connection = $connection;
            $this->parentTransaction = $parent;
            $this->superUser = $superUser;
            $this->connection->autocommit(false);
            $this->connection->startTransaction();
            
        }catch (\Exception $e){
            throw new TransactionNotActiveException($e->getMessage());
        }
    }

    /**
     * @param Connection $connection
     * @return Transaction
     */
    function createSubTransaction($connection = null,$superUser = null){
        if($connection == null){
            $connection = $this->connection;
        }
        if($superUser === null){
            $superUser = $this->superUser;
        }
        $subTransaction = new Transaction($connection,$this,$superUser);
        return $subTransaction;
    }


    function abortTransaction(){
        // rollback current transaction
        $this->connection->abortTransaction();
        return $this->parentTransaction;
    }

    function commitTransaction(){
    	$this->connection->commitTransaction();
        return $this->parentTransaction;
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
    
}

class TransactionManager {

    /**
     * @var Transaction
     */
    private static $transaction = null;

    /**
     * @param Connection $conection
     * @param boolean $superUser
     * @param string $timezone
     */
    static function startTransaction($conection = null,$superUser = null,$timezone = null){
        if(self::$transaction == null){
            if($superUser === null ){
                $superUser = false;
            }
            self::$transaction = new Transaction($conection,null,$superUser);
        }else{
            self::$transaction = self::$transaction->createSubTransaction($conection,$superUser);
        }
        self::setTimeZone($timezone);
    }

    static function commitTransaction(){
        if(self::$transaction == null){
            throw new TransactionNotActiveException();
        }
        self::$transaction = self::$transaction->commitTransaction();
        self::setTimeZone();
    }

    static function abortTransaction(){
        if(self::$transaction == null){
            throw new TransactionNotActiveException();
        }
        self::$transaction = self::$transaction->abortTransaction();
        self::setTimeZone();
    }

    /**
     * @throws TransactionNotActiveException
     * @return Connection
     */
    static function getConnection(){
        if(self::$transaction == null){
            throw new TransactionNotActiveException();
        }
        return self::$transaction->getConnection();
    }

    /**
     * @return boolean
     */
    static function isSuperUser(){
        if(is_object(self::$transaction)){
            return self::$transaction->isSuperUser();
        }else{
            return false;
        }
    }
    
    static function getAttribute($name){
        if(self::$transaction == null){
            return null;
        }
        return self::$transaction->getAttribute($name);
    }
    
    static function setAttribute($name,$value){
        if(self::$transaction == null){
            throw new TransactionNotActiveException();
        }
        self::$transaction->setAttribute($name, $value);
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
    		
    		call_user_func_array($callback,$parameters);
    		
    		self::commitTransaction();
    	}catch (\Exception $e){
    		self::abortTransaction();
    		throw $e;
    	}
    }
    
    private static function setTimeZone($timeZone = null){
    	if($timeZone == null){
    		if(isset(self::$transaction)){
    			$timeZone = self::$transaction->getAttribute('timeZone');
    		}else{
    			$timeZone = date_default_timezone_get();
    		}
    	}
    	date_default_timezone_set($timeZone);
    	$transaction = self::$transaction;
    	if($transaction != null){
    		$transaction->getConnection()->setTimeZone($timeZone);
    		$transaction->setAttribute('timeZone', $timeZone);
    	}
    }

}