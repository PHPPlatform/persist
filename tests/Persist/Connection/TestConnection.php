<?php
namespace PhpPlatform\Tests\Persist\Connection;

use PhpPlatform\Persist\Connection\ConnectionFactory;
use PhpPlatform\Tests\Persist\ModelTest;
use PhpPlatform\Persist\Exception\InvalidInputException;

class TestConnection extends ModelTest{
	
	function testFormatDate(){
		$connection = ConnectionFactory::getConnection();
		
		$date = $connection->formatDate();
		parent::assertEquals(date("Y-m-d",time()), $date);
		
		$date = $connection->formatDate(null,true);
		parent::assertStringStartsWith(date("Y-m-d H:i",time()), $date);
		
		$date = $connection->formatDate(time()-(24*60*60),true); // yesterday
		parent::assertStringStartsWith(date("Y-m-d H:i",time()-(24*60*60)), $date);
		
		$isException = false;
		try{
			$date = $connection->formatDate(array(),true); //Wrong input
		}catch (InvalidInputException $e){
			$isException = true;
			parent::assertEquals("1st parameter should be either a string or integer or null", $e->getMessage());
		}
		parent::assertTrue($isException);
	}
	
	function testFormatTime(){
		$connection = ConnectionFactory::getConnection();
		
		$time = $connection->formatTime();
		parent::assertEquals("00:00:00", $time);
		
		$time = $connection->formatTime(1);
		parent::assertEquals("01:00:00", $time);
		
		$time = $connection->formatTime(12,1,20);
		parent::assertEquals("00:01:20", $time);
		
		$time = $connection->formatTime(8,1,22,"PM");
		parent::assertEquals("20:01:22", $time);
		
		$isException = false;
		try{
			$time = $connection->formatTime(12,1,22,"PM"); //Wrong input
		}catch (InvalidInputException $e){
			$isException = true;
			parent::assertEquals("invalid parameters", $e->getMessage());
		}
		parent::assertTrue($isException);
		
		
		$isException = false;
		try{
			$time = $connection->formatTime(20); //Wrong input
		}catch (InvalidInputException $e){
			$isException = true;
			parent::assertEquals("invalid parameters", $e->getMessage());
		}
		parent::assertTrue($isException);
		
	}
	
}