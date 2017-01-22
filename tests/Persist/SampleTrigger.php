<?php

namespace PhpPlatform\Tests\Persist;

use PhpPlatform\Persist\Model;

class SampleTrigger {
	
    public static function createPreTrigger(Model $object){
		self::logTriggerInvocation("createPreTrigger", array($object));
	}
	
	public static function createPostTrigger(Model $object){
		self::logTriggerInvocation("createPostTrigger", array($object));
	}

	public static function readPreTrigger($args){
		self::logTriggerInvocation("readPreTrigger", array($args));
	}
	
	public static function readPostTrigger($results){
		self::logTriggerInvocation("readPostTrigger", array($results));
	}
	
	public static function updatePreTrigger(Model $object,$modifiedValues){
		self::logTriggerInvocation("updatePreTrigger", array($object,$modifiedValues));
	}
	
	public static function updatePostTrigger(Model $object,$modifiedValues){
		self::logTriggerInvocation("updatePostTrigger", array($object,$modifiedValues));
	}
	
	public static function deletePreTrigger(Model $object){
		self::logTriggerInvocation("deletePreTrigger", array($object));
	}
	
	public static function deletePostTrigger(Model $object){
		self::logTriggerInvocation("deletePostTrigger", array($object));
	}
	
	private static function logTriggerInvocation($name,$parameters){
		if(!isset($_ENV[TRIGGER_TEST_LOG][$name])){
			$_ENV[TRIGGER_TEST_LOG][$name] = array();
		}
		$_ENV[TRIGGER_TEST_LOG][$name][] = $parameters;
	}
	
}