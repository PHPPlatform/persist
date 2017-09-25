<?php

namespace PhpPlatform\Persist;

use PhpPlatform\Persist\Exception\InvalidInputException;

class Field {
	private $className = null;
	private $fieldName = null;
	
	function __construct($className,$fieldName){
		if(is_subclass_of($className, 'PhpPlatform\Persist\Model') && Reflection::hasProperty($className, $fieldName)){
			$this->className = $className;
			$this->fieldName = $fieldName;
		}else{
			throw new InvalidInputException('Invalid class and field names');
		}
	}
	
	
	public function getClassName() {
		return $this->className;
	}
	public function getFieldName() {
		return $this->fieldName;
	}
	
	public function asString(){
		return $this->className.'::'.$this->fieldName;
	}
	
	
	
}