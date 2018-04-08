<?php

namespace PhpPlatform\Persist\Connection;

use PhpPlatform\Persist\Connection\Connection;
use PhpPlatform\Errors\Exceptions\Persistence\NoConnectionException;
use PhpPlatform\Config\Settings;
use PhpPlatform\Persist\Exception\InvalidInputException;

class MySql extends \mysqli implements Connection{
	
	private $sqlLogFile = null;
	private $outputDateFormat = null;
	private $outputTimeFormat = null;
	private $outputDateTimeFormat = null;
	
	private $transactionCount = 0;
	
	private $currentTimeZone = null;
	
	function __construct($params){
		if (extension_loaded('mysqli')) {
			
			$host       = $params['host'];
			$username   = $params['username'];
			$password   = $params['password'];
			$dbname     = $params['dbname'];
			$port       = $params['port'];
			$socket     = $params['socket'];
			
			$this->outputDateFormat     = $params['outputDateFormat'];
			$this->outputTimeFormat     = $params['outputTimeFormat'];
			$this->outputDateTimeFormat = $params['outputDateTimeFormat'];
			
			$this->sqlLogFile = Settings::getSettings('php-platform/persist','sqlLogFile');
				
			parent::__construct($host,$username,$password,$dbname,$port,$socket);
			
			if (mysqli_connect_error()) {
				Throw new NoConnectionException("Error Connecting to Database. Please check your configuration , Cause ".mysqli_connect_error());
			}
		} else {
			throw new NoConnectionException("Fatal Error :  mysqli extension is not loaded");
		}
	}


    /**
	 * @param string $queryString
	 */
	function query($queryString){
		$this->log($queryString);
		return parent::query($queryString);
	}
	
	/**
	 * this method turns on/off the auto committing of query
	 *
	 * @param boolean $autocommit
	 */
	function autocommit($mode){
		return parent::autocommit($mode);
	}
	
	/**
	 * this method returns the error from last query , if any
	 *
	 * @return string
	 */
	function lastError(){
		return $this->error;
	}
	
	/**
	 * this method returns the last auto incremented id
	 *
	 * @return int
	 */
	function lastInsertedId(){
		return $this->insert_id;
	}
	
	/**
	 * this method closes the connection
	 */
	function close(){
		parent::close();
	}
	
	
	// transaction methods
	
	/**
	 * starts the transaction
	 * 
	 * @return integer , number of live transactions after executing this method
	 */
	function startTransaction(){
		$this->transactionCount++;
		$this->query("SAVEPOINT SAVE_POINT_".$this->transactionCount);
		return $this->transactionCount;
	}
	
	/**
	 * commits the last transaction
	 * 
	 * @return integer , number of live transactions after executing this method
	 */
	function commitTransaction(){
		$this->log("COMMIT SAVE_POINT_".$this->transactionCount);
		$this->transactionCount--;
		if($this->transactionCount == 0){
			parent::commit();
			$this->log("COMMIT REAL");
		}
		return $this->transactionCount;
	}
	
	/**
	 * aborts the last transaction
	 * 
	 * @return integer , number of live transactions after executing this method
	 */
	function abortTransaction(){
		// rollback current transaction
		$this->query("ROLLBACK TO SAVE_POINT_".$this->transactionCount);
		$this->transactionCount--;
		return $this->transactionCount;
	}
	
	// sql injection
	
	/**
	 * this method encodes the value to nullify SQL Injection
	 *
	 * @param string $value
	 * @return string safe value
	 */
	function encodeForSQLInjection($value){
		return parent::real_escape_string($value);
	}
	
	// data type format methods
	
	/**
	 * this method sets the timezone for this connection
	 * @param string $timeZone , time zone in php
	 */
	function setTimeZone($timeZone = null){
	    
	    if($timeZone == null){
	        $timeZone = date_default_timezone_get();
	    }
		
		//convert php timezone into mysql timezone format
    	$dtz = new \DateTimeZone($timeZone);
    	$timeInTimeZone = new \DateTime('now', $dtz);
    	
    	$sign = "+";
    	$offset = $dtz->getOffset( $timeInTimeZone ) / 3600;
    	if($offset < 0){
    		$sign = "-";
    		$offset = -1 * $offset;
    	}
    	$hourPart = intval($offset);
    	$minutePart = $offset-$hourPart;
    	$minutePart = $minutePart * 60;
    	
    	$timeZoneForMySql = $sign.$hourPart.":".$minutePart;
    	
    	// set the timezone in mysql
    	$this->query("SET time_zone='$timeZoneForMySql'");
    	
    	$this->currentTimeZone = $timeZone;
    }
    
    /**
     * {@inheritDoc}
     * @see \PhpPlatform\Persist\Connection\Connection::getTimeZone()
     */ 
    public function getTimeZone() {
        return $this->currentTimeZone;
    }
	
	/**
	 * this method formats date for this connection
	 * 
	 * @param string|integer $dateStr time in string or timestamp in integer
	 * @param boolean $includeTime
	 * 
	 * @return string , formatted date as string
	 * 
	 */
	function formatDate($dateStr=null,$includeTime=null){
		if($dateStr === null){
			$date = time();
		}else if(is_string($dateStr)){
			$date = strtotime($dateStr);
		}else if(is_integer($dateStr)){
			$date = $dateStr;
		}else{
			throw new InvalidInputException('1st parameter should be either a string or integer or null');
		}
		$format = "Y-m-d";
		if(isset($includeTime)){
			$format .= " H:i:s";
		}
		$mysqlDate = date($format,$date);
		return $mysqlDate;
	}
	
	/**
	 * this method formats time for this connection
	 * 
	 * @param number $hh
	 * @param number $mm
	 * @param number $ss
	 * @param string $ampm
	 * 
	 * @return string represenation of time
	 */
	function formatTime($hh=0,$mm=0,$ss=0,$ampm="AM"){
		
		$ampm = strtoupper($ampm);
		
		$hhUpperBound = 12;
		if($ampm == "PM"){
			$hhUpperBound--;
		}
		
		if(!( (0 <= $hh && $hh <= $hhUpperBound) && 
			  (0 <= $mm && $mm <= 60) &&
			  (0 <= $ss && $ss <= 60)	)
				){
			throw new InvalidInputException('invalid parameters');
		}
		
		if($ampm == "PM"){
			if($hh != 12){
				$hh = $hh+12;
			}
		}else if($ampm == "AM"){
			if($hh == 12){
				$hh = 0;
			}
		}else{
			throw new InvalidInputException('last parameter should be either a AM or PM in string');
		}
		if($hh < 10){
			$hh = "0".$hh;
		}
		
		if($mm < 10){
			$mm = "0".$mm;
		}
		
		if($ss < 10){
			$ss = "0".$ss;
		}
		
		$mysqlTime = $hh.":".$mm.":".$ss;
		return $mysqlTime;
	}
	
	/**
	 * this method formats boolean for this connection
	 * 
	 * @param boolean|string $value
	 */
	function formatBoolean($value){
		if(is_string($value)){
			if(strtoupper($value) == "TRUE"){
				$value = '1';
			}else if(strtoupper($value) == "FALSE"){
				$value = '0';
			}else{
				throw new InvalidInputException("Not a boolean value");
			}
		}elseif ($value == true){
			$value = '1';
		}elseif ($value == false){
			$value = '0';
		}else{
			throw new InvalidInputException("Not a boolean value");
		}
		return $value;
	}
	
	/**
	 * this method returns output date format for this connection
	 */
	function outputDateFormat(){
		return $this->outputDateFormat;
	}
	
	/**
	 * this method returns output date format for this connection
	 */
	function outputTimeFormat(){
		return $this->outputTimeFormat;
	}
	
	/**
	 * this method returns output datetime format for this connection
	 */
	function outputDateTimeFormat(){
		return $this->outputDateTimeFormat;
	}
	
    private function log($message){
    	if(isset($this->sqlLogFile)){
    		if(!file_exists(dirname($this->sqlLogFile))){
    			mkdir(dirname($this->sqlLogFile), 0777, true);
    		}
    		error_log($message.PHP_EOL,3,$this->sqlLogFile);
    	}
    }

}