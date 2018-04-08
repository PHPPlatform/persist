<?php

namespace PhpPlatform\Persist\Connection;

interface Connection {
	
	/**
	 * @param string $queryString
	 */
	function query($queryString);
	
	/**
	 * this method turns on/off the auto committing of query
	 * 
	 * @param boolean $mode
	 */
	function autocommit($mode);
	
	/**
	 * this method returns the error from last query , if any
	 * 
	 * @return string 
	 */
	function lastError();
	
	/**
	 * this method returns the last auto incremented id
	 *
	 * @return int
	 */
	function lastInsertedId();
	
	
	/**
	 * this method closes the connection
	 */
	function close();
	
	// transaction methods
	
	/**
	 * starts the transaction
	 * 
	 * @return integer , number of live transactions after executing this method
	 */
	function startTransaction();
	
	/**
	 * commits the last transaction
	 * 
	 * @return integer , number of live transactions after executing this method
	 */
	function commitTransaction();
	
	/**
	 * aborts the last transaction
	 * 
	 * @return integer , number of live transactions after executing this method
	 */
	function abortTransaction();
	
	
	// sql injection 
	
	/**
	 * this method encodes the value to nullify SQL Injection
	 * 
	 * @param string $value
	 */
	function encodeForSQLInjection($value);
	
	
	// data type format methods
	
	/**
	 * this method sets the timezone for this connection
	 * @param string $timeZone , time zone in php, if null then return value from php's date_default_timezone_get() method is considered
	 */
	function setTimeZone($timeZone = null);
	
	/**
	 * this method gets the timezone from this connection
	 * @return string $timeZone , time zone in php
	 */
	function getTimeZone();
	
	/**
	 * this method formats date for this connection
	 * 
	 * @param string|integer $dateStr time in string or timestamp in integer
	 * @param boolean $includeTime
	 * 
	 * @return string , formatted date as string
	 * 
	 */
	function formatDate($dateStr=null,$includeTime=null);
	
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
	function formatTime($hh=0,$mm=0,$ss=0,$ampm="AM");
	
	/**
	 * this method formats boolean for this connection
	 * 
	 * @param boolean|string $value
	 */
	function formatBoolean($value);
	
	/**
	 * this method returns output date format for this connection
	 */
	function outputDateFormat();
	
	/**
	 * this method returns output date format for this connection
	 */
	function outputTimeFormat();
	
	/**
	 * this method returns output datetime format for this connection
	 */
	function outputDateTimeFormat();
	
}