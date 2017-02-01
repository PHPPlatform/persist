<?php
/**
 * User: Raaghu
 * Date: 8/7/13
 * Time: 2:38 PM
 */
namespace PhpPlatform\Persist;

use PhpPlatform\Persist\Exception\TransactionNotActiveException;

class Transaction {

    /**
     * @var MySql|null
     */
    private $dbs = null;

    /**
     * @var Transaction
     */
    private $parentTransaction = null;

    /**
     * @var boolean
     */
    private $superUser = null;

    /**
     * @var int
     */
    private $rollbackId = null;
    
    /**
     * @var array
     */
    private $attributes = null;

    /**
     * @param MySql $dbs
     * @param Transaction $parent
     * @param boolean $superUser
     */
    function __construct($dbs = null,$parent = null,$superUser = false){
        try{
            if($dbs == null){
                $dbs = MySql::getInstance(true);
            }
            $dbs->autocommit(false);
            $this->dbs = $dbs;
            $this->parentTransaction = $parent;
            $this->superUser = $superUser;
            if(isset($parent)){
                $this->rollbackId = $parent->rollbackId + 1;
            }else{
                $this->rollbackId = 1;
            }
            $this->dbs->query("SAVEPOINT RB_".$this->rollbackId);
        }catch (\Exception $e){
            throw new TransactionNotActiveException($e->getMessage());
        }
    }

    /**
     * @param MySql $dbs
     * @return Transaction
     */
    function createSubTransaction($dbs = null,$superUser = null){
        /**
         * @TODO : Improve transaction manager to work more than one connections, i.e., if new $dbs is passed while creating subTransaction
         */
        if($dbs == null){
            $dbs = $this->dbs;
        }
        if($superUser === null){
            $superUser = $this->superUser;
        }

        $subTransaction = new Transaction($dbs,$this,$superUser);
        return $subTransaction;
    }


    function abortTransaction(){

        // rollback current transaction
        $this->dbs->query("ROLLBACK TO RB_".$this->rollbackId);

        // close the db connection
        if($this->rollbackId == 1){
            $this->dbs->close();
        }

        return $this->parentTransaction;
    }

    function commitTransaction(){

        if($this->rollbackId == 1){
            $this->dbs->commit();
            $this->dbs->close();
        }

        return $this->parentTransaction;
    }

    function getConnection(){
        return $this->dbs;
    }

    function isSuperUser(){
        return $this->superUser;
    }
    
    function getAttribute($name){
        return $this->attributes[$name];
    }
    
    function setAttribute($name,$value){
        if($name == "timezone"){
            $currentTimeZone = date_default_timezone_get();
            if($currentTimeZone != $value){
                if(!date_default_timezone_set($value)){
                    new \Exception("Invalid TimeZone Id : $value, setting to UTC");
                    $value = 'UTC';
                    date_default_timezone_set($value);
                }
                
                $mysqlTimeZone = MySql::getMysqlTimeZone($value);
                $this->dbs->query("SET time_zone='$mysqlTimeZone'");
            }
        }
        $this->attributes[$name] = $value;
        return $this;
    }

}

class TransactionManager {

    /**
     * @var Transaction
     */
    static $transaction = null;

    /**
     * @param $dbs MySql
     */
    static function startTransaction($dbs = null,$superUser = null,$timezone = null){
        if(self::$transaction == null){
            if($superUser === null ){
                $superUser = false;
            }
            self::$transaction = new Transaction($dbs,null,$superUser);
            if($timezone === null){
            	if(isset($_SERVER['HTTP_TIME_ZONE'])){
            		// read from request header
            		$timezone = $_SERVER['HTTP_TIME_ZONE'];
            		setcookie('TIME_ZONE',$timezone,time()+60*60*24*30);
            	}else if(isset($_COOKIE['TIME_ZONE'])){
            		// read from cookiee
            		$timezone = $_COOKIE['TIME_ZONE'];
            	}else{
            		// else set to default UTC
            		$timezone = 'UTC';
            	}
            }
        }else{
            self::$transaction = self::$transaction->createSubTransaction($dbs,$superUser);
            if($timezone === null){
                $timezone = self::$transaction->getAttribute("timezone");
            }
        }
        self::$transaction->setAttribute("timezone", $timezone);
    }

    static function commitTransaction(){
        if(self::$transaction == null){
            throw new TransactionNotActiveException();
        }
        self::$transaction = self::$transaction->commitTransaction();
        if(self::$transaction != null){
        	self::$transaction->setAttribute("timezone", self::$transaction->getAttribute("timezone"));
        }
    }

    static function abortTransaction(){
        if(self::$transaction == null){
            throw new TransactionNotActiveException();
        }
        self::$transaction = self::$transaction->abortTransaction();
        if(self::$transaction != null){
        	self::$transaction->setAttribute("timezone", self::$transaction->getAttribute("timezone"));
        }
    }

    /**
     * @throws TransactionNotActiveException
     * @return MySql
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
            throw new TransactionNotActiveException();
        }
        return self::$transaction->getAttribute($name);
    }
    
    static function setAttribute($name,$value){
        if(self::$transaction == null){
            throw new TransactionNotActiveException();
        }
        self::$transaction->setAttribute($name, $value);
    }
    
    static function executeInTransaction($callback, $parameters = array(), $superUser = null,$dbs = null,$timeZone = null){
    	try{
    		self::startTransaction($dbs,$superUser,$timeZone);
    		
    		call_user_func_array($callback,$parameters);
    		
    		self::commitTransaction();
    	}catch (\Exception $e){
    		self::abortTransaction();
    		throw $e;
    	}
    }

}