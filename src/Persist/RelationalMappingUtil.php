<?php

namespace PhpPlatform\Persist;

class RelationalMappingUtil {
	
	private static $relationalMappings = array();
	const TABLE_NAME_GENERATOR = "generateTableName";
	
	public static function getPrimaryKey(&$classInfo){
		if(array_key_exists('primayFieldName', $classInfo)){
			return  $classInfo['primayFieldName'];
		}else{
			foreach ($classInfo['fields'] as $fieldName=>$field){
				if(self::_isPrimary($field)){
					$classInfo['primayFieldName'] = $fieldName;
					return $fieldName;
				}
			}
		}
		return null;
	}
	
	public static function getAutoIncrementKey(&$classInfo){
		if(array_key_exists('autoIncrementFieldName', $classInfo)){
			return  $classInfo['autoIncrementFieldName'];
		}else{
			foreach ($classInfo['fields'] as $fieldName=>$field){
				if(self::_isAutoIncrement($field) ){
					$classInfo['autoIncrementFieldName'] = $fieldName;
					return $fieldName;
				}
			}
		}
		return null;
	}
	
	public static function getReferenceKey(&$classInfo){
		if(array_key_exists('referenceFieldName', $classInfo)){
			return  $classInfo['referenceFieldName'];
		}else{
			foreach ($classInfo['fields'] as $fieldName=>$field){
				if(self::_isReference($field)){
					$classInfo['referenceFieldName'] = $fieldName;
					return $fieldName;
				}
			}
		}
		return null;
	}
	
	public static function _isSet(&$field){
		return isset($field['set']) && (strtoupper($field['set']) == "TRUE" || $field['set'] === true);
	}
	
	public static function _isGet(&$field){
		return isset($field['get']) && (strtoupper($field['get']) == "TRUE" || $field['get'] === true);
	}
	
	public static function _isPrimary(&$field){
		return isset($field['primary']) && (strtoupper($field['primary']) == "TRUE" || $field['primary'] === true);
	}
	
	public static function _isReference(&$field){
		return isset($field['reference']) && (strtoupper($field['reference']) == "TRUE" || $field['reference'] === true);
	}
	
	public static function _isAutoIncrement(&$field){
		return isset($field['autoIncrement']) && (strtoupper($field['autoIncrement']) == "TRUE" || $field['autoIncrement'] === true);
	}
	
	public static function _isForeignField(&$field){
		return isset($field['foreignField']);
	}
	
	public static function getClassConfiguration($className){
		if(!is_string($className)){
			$className = get_class($className);
		}
		
		$classList = array();
		while(false !== $className){
			
			if(array_key_exists($className, self::$relationalMappings)){
				$relationalMapping = self::$relationalMappings[$className];
			}else{
				$relationalMapping = RelationalMappingCache::getInstance()->get($className);
				self::$relationalMappings[$className] = $relationalMapping;
			}
			if($relationalMapping !== false){
				$classList[$className] = $relationalMapping;
			}
			$className = get_parent_class($className);
		}
		return $classList;
	}
	
	public static function getTableName(&$classInfo,$object){
		if(is_string($object)){
			$className = $object;
			$object = null;
		}else{
			$className = get_class($object);
		}
		if(!isset($classInfo["realTableName"])){
			if(Reflection::hasMethod($className, self::TABLE_NAME_GENERATOR)){
				$classInfo["realTableName"] = Reflection::invokeArgs($className, self::TABLE_NAME_GENERATOR, null,array($classInfo));
			}else if(isset($classInfo["tableName"])){
				$classInfo["realTableName"] = $classInfo["tableName"];
			}
		}
		return $classInfo["realTableName"];
	}
	
	
}