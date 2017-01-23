<?php

namespace PhpPlatform\Persist;

use PhpPlatform\JSONCache\Cache;
use PhpPlatform\Annotations\Annotation;

final class RelationalMappingCache extends Cache {
	private static $cacheObj = null;
	protected $cacheFileName = "RelationalMappingCache2ghtgf4ub4ju"; // cache for settings
	
	/**
	 * 
	 * @return RelationalMappingCache
	 */
	public static function getInstance() {
		if (self::$cacheObj == null) {
			self::$cacheObj = new RelationalMappingCache();
		}
		return self::$cacheObj;
	}
	
	/**
	 *
	 * @param $className
	 * @return mixed
	 */
	function get($className) {
		$resultAnnotations = parent::getData ( preg_replace ( '/\/|\\\\/', ".", $className ) );
		if ($resultAnnotations == null) {
			try {
				if (! class_exists ( $className, true )) {
					throw new \Exception ( "Class does not exist" );
				}
				
				$defaultClassAnnotations = array (
						"tableName" => null,
						"prefix" => null 
				);
				$defaultPropertyAnnotations = array (
						"columnName" => null,
						"type" => null,
						"primary" => true,
						"autoIncrement" => true,
						"reference" => true,
						"set" => true,
						"get" => true,
						"foreignField" => null,
						"groupBy" => true,
						"group" => true 
				);
				
				$annotations = Annotation::getAnnotations ( $className );
				
				if ($annotations ["class"] == false) {
					throw new \Exception ( "Error in getting exceptions" );
				}
				
				$classAnnotations = $annotations ["class"];
				foreach ( $classAnnotations as $annotationName => $annotationValue ) {
					if ($annotationValue == null && isset ( $defaultClassAnnotations [$annotationName] )) {
						$classAnnotations [$annotationName] = $defaultClassAnnotations [$annotationName];
					}
				}
				$classAnnotations ["tableName"] = $classAnnotations ["tableName"] ;
				$resultAnnotations = $classAnnotations;
				
				$fields = array ();
				if (array_key_exists ( "properties", $annotations ) && is_array ( $annotations ["properties"] )) {
					foreach ( $annotations ["properties"] as $propertyName => $propertyAnnotations ) {
						foreach ( $propertyAnnotations as $annotationName => $annotationValue ) {
							if ($annotationValue == null && isset ( $defaultPropertyAnnotations [$annotationName] )) {
								$propertyAnnotations [$annotationName] = $defaultPropertyAnnotations [$annotationName];
							}
						}
						$fields [$propertyName] = $propertyAnnotations;
					}
				}
				
				$resultAnnotations ["fields"] = $fields;
				
				$absoluteMappings = $resultAnnotations;
				foreach (array_reverse(preg_split('/\/|\\\/', $className)) as $packagePath){
					$absoluteMappings = array($packagePath=>$absoluteMappings);
				}
				
				parent::setData($absoluteMappings);
				
			} catch ( \Exception $e ) {
				return FALSE;
			}
		}
		return $resultAnnotations;
	}

}