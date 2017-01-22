<?php

namespace PhpPlatform\Persist;

class Reflection {
	/**
	 * @var \ReflectionClass[]
	 */
	private static $reflectionClasses = array();

	
	/**
	 * @var \ReflectionProperty[]
	 */
	private static $reflectionProperties = array();
	

	/**
	 * @var \ReflectionMethod[]
	 */
	private static $reflectionMethods = array();
	
	
	public static function getValue($className,$propertyName,$object){
		return self::getReflectionProperty($className, $propertyName)->getValue($object);
	}
	
	public static function setValue($className,$propertyName,$object,$value){
		return self::getReflectionProperty($className, $propertyName)->setValue($object,$value);
	}
	
	public static function invokeArgs($className,$methodName,$object,$args = array()){
		return self::getReflectionMethod($className, $methodName)->invokeArgs($object, $args);
	}
	
	public static function newInstanceArgs($className,$args = array()){
		return self::getReflectionClass($className)->newInstanceArgs($args);
	}
	
	/**
	 *
	 * @param unknown $className
	 * @param unknown $propertyName
	 * @return ReflectionProperty
	 */
	private static function getReflectionClass($className){
		if(isset(self::$reflectionClasses[$className])){

		}else{
			$reflectionClass = new \ReflectionClass($className);
			self::$reflectionClasses[$className] = $reflectionClass;
		}
		return self::$reflectionClasses[$className];
	}
	
	/**
	 * 
	 * @param unknown $className
	 * @param unknown $propertyName
	 * @return ReflectionProperty
	 */
	private static function getReflectionProperty($className,$propertyName){
		if(isset(self::$reflectionProperties[$className.'::'.$propertyName])){
				
		}else if(isset(self::$reflectionClasses[$className])){
			$reflectionProperty = self::$reflectionClasses[$className]->getProperty($propertyName);
			$reflectionProperty->setAccessible(true);
			self::$reflectionProperties[$className.'::'.$propertyName] = $reflectionProperty;
		}else{
			$reflectionClass = new \ReflectionClass($className);
			self::$reflectionClasses[$className] = $reflectionClass;
			$reflectionProperty = $reflectionClass->getProperty($propertyName);
			$reflectionProperty->setAccessible(true);
			self::$reflectionProperties[$className.'::'.$propertyName] = $reflectionProperty;
		}
		return self::$reflectionProperties[$className.'::'.$propertyName];
	}
	
	/**
	 * 
	 * @param unknown $className
	 * @param unknown $methodName
	 * @return ReflectionMethod
	 */
	private static function getReflectionMethod($className,$methodName){
		if(isset(self::$reflectionMethods[$className.'::'.$methodName])){
	
		}else if(isset(self::$reflectionClasses[$className])){
			$reflectionMethod = self::$reflectionClasses[$className]->getMethod($methodName);
			$reflectionMethod->setAccessible(true);
			self::$reflectionMethods[$className.'::'.$methodName] = $reflectionMethod;
		}else{
			$reflectionClass = new \ReflectionClass($className);
			self::$reflectionClasses[$className] = $reflectionClass;
			$reflectionMethod = $reflectionClass->getMethod($methodName);
			$reflectionMethod->setAccessible(true);
			self::$reflectionMethods[$className.'::'.$methodName] = $reflectionMethod;
		}
		return self::$reflectionMethods[$className.'::'.$methodName];
	}
}