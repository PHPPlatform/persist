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
	 * this method formats date for this connection
	 * 
	 * @param string $dateStr
	 * @param boolean $includeTime
	 * @param boolean $dateStrIsTimestamp
	 */
	function formatDate($dateStr=null,$includeTime=null,$dateStrIsTimestamp = false);
	
	/**
	 * this method formats time for this connection
	 * 
	 * @param string $ampm
	 * @param number $hh
	 * @param number $mm
	 * @param number $ss
	 */
	function formatTime($ampm="AM",$hh=0,$mm=0,$ss=0);
	
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
	
	/**
	 * this method sets the timezone for this connection
	 * @param string $timeZone , time zone in php
	 */
	function setTimeZone($timeZone);
	
}