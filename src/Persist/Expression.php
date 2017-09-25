<?php

namespace PhpPlatform\Persist;

use PhpPlatform\Persist\Exception\InvalidInputException;

class Expression {
	
	private static $IS_EXPRESSION = 1;
	private static $IS_FIELD = 2;
	private static $IS_STRING = 4;
	private static $IS_NUM = 8;
	private static $IS_BOOLEAN = 16;
	private static $IS_ARRAY = 32; // array of scalars only
	
	
	
	private $operator = null;
	private $operands = null;
	
	function __construct($operator,$operands){
		$allowedOperators = array(
			Model::OPERATOR_LIKE,
			Model::OPERATOR_EQUAL,
			Model::OPERATOR_NOT_EQUAL,
			Model::OPERATOR_LT,
			Model::OPERATOR_GT,
			Model::OPERATOR_LTE,
			Model::OPERATOR_GTE,
			Model::OPERATOR_BETWEEN,
			Model::OPERATOR_IN,
			Model::OPERATOR_IS_NULL,
			Model::OPERATOR_IS_NOT_NULL,
			Model::OPERATOR_AND,
			Model::OPERATOR_OR
		);
		/**
		 * @todo to support Aggregator functions like , SUM , MAX, MIN ... 
		 * @todo extend support for IS NULL and IS NOT NULL
		 */
		
		if(!in_array($operator, $allowedOperators)){
			throw new InvalidInputException('1st parameter is not a valid operator');
		}
		$this->operator = $operator;
		
		
		if(!is_array($operands)){
			throw new InvalidInputException('2nd parameter is not a array of operands');
		}
		
		switch ($this->operator){
			case Model::OPERATOR_LIKE:
				$this->expectedOperandsLength($operands, 2);
				$this->isValidOpearand($operands[0], self::$IS_FIELD|self::$IS_STRING|self::$IS_NUM);
				$this->isValidOpearand($operands[1], self::$IS_STRING);
				break;
			case Model::OPERATOR_EQUAL:
				$this->expectedOperandsLength($operands, 2);
				$this->isValidOpearand($operands[0], self::$IS_FIELD|self::$IS_STRING|self::$IS_NUM|self::$IS_BOOLEAN);
				$this->isValidOpearand($operands[1], self::$IS_FIELD|self::$IS_STRING|self::$IS_NUM|self::$IS_BOOLEAN);
				break;
			case Model::OPERATOR_NOT_EQUAL:
				$this->expectedOperandsLength($operands, 2);
				$this->isValidOpearand($operands[0], self::$IS_FIELD|self::$IS_STRING|self::$IS_NUM|self::$IS_BOOLEAN);
				$this->isValidOpearand($operands[1], self::$IS_FIELD|self::$IS_STRING|self::$IS_NUM|self::$IS_BOOLEAN);
				break;
			case Model::OPERATOR_LT:
				$this->expectedOperandsLength($operands, 2);
				$this->isValidOpearand($operands[0], self::$IS_FIELD|self::$IS_NUM);
				$this->isValidOpearand($operands[1], self::$IS_FIELD|self::$IS_NUM);
				break;
			case Model::OPERATOR_GT:
				$this->expectedOperandsLength($operands, 2);
				$this->isValidOpearand($operands[0], self::$IS_FIELD|self::$IS_NUM);
				$this->isValidOpearand($operands[1], self::$IS_FIELD|self::$IS_NUM);
				break;
			case Model::OPERATOR_LTE:
				$this->expectedOperandsLength($operands, 2);
				$this->isValidOpearand($operands[0], self::$IS_FIELD|self::$IS_NUM);
				$this->isValidOpearand($operands[1], self::$IS_FIELD|self::$IS_NUM);
				break;
			case Model::OPERATOR_GTE:
				$this->expectedOperandsLength($operands, 2);
				$this->isValidOpearand($operands[0], self::$IS_FIELD|self::$IS_NUM);
				$this->isValidOpearand($operands[1], self::$IS_FIELD|self::$IS_NUM);
				break;
			case Model::OPERATOR_BETWEEN:
				$this->expectedOperandsLength($operands, 3);
				$this->isValidOpearand($operands[0], self::$IS_FIELD|self::$IS_NUM|self::$IS_STRING);
				$this->isValidOpearand($operands[1], self::$IS_NUM|self::$IS_STRING);
				$this->isValidOpearand($operands[2], self::$IS_NUM|self::$IS_STRING);
				break;
			case Model::OPERATOR_IN:
				$this->expectedOperandsLength($operands, 2);
				$this->isValidOpearand($operands[0], self::$IS_FIELD|self::$IS_NUM|self::$IS_STRING);
				$this->isValidOpearand($operands[1], self::$IS_ARRAY);
				break;
			case Model::OPERATOR_IS_NULL:
				$this->expectedOperandsLength($operands, 1);
				$this->isValidOpearand($operands[0], self::$IS_FIELD);
				break;
			case Model::OPERATOR_IS_NOT_NULL:
				$this->expectedOperandsLength($operands, 1);
				$this->isValidOpearand($operands[0], self::$IS_FIELD);
				break;
			case Model::OPERATOR_AND:
				$this->expectedOperandsLength($operands, 2, true);
				foreach ($operands as $operand){
					$this->isValidOpearand($operand, self::$IS_EXPRESSION|self::$IS_BOOLEAN);
				}
				break;
			case Model::OPERATOR_OR:
				$this->expectedOperandsLength($operands, 2, true);
				foreach ($operands as $operand){
					$this->isValidOpearand($operand, self::$IS_EXPRESSION|self::$IS_BOOLEAN);
				}
				break;
			default:
				throw new InvalidInputException('Not a valid operator');
		}
		$this->operands = $operands;
		
	}
	
	private function isValidOpearand($operand,$flag){
		$valid = false;
		if(($flag & self::$IS_EXPRESSION) && $operand instanceof Expression){
			$valid = true;
		}
		
		if(!$valid && ($flag & self::$IS_FIELD) && $operand instanceof Field){
			$valid = true;
		}
		
		if(!$valid && ($flag & self::$IS_STRING) && is_string($operand)){
			$valid = true;
		}
		
		if(!$valid && ($flag & self::$IS_NUM) && is_numeric($operand)){
			$valid = true;
		}
		
		if(!$valid && ($flag & self::$IS_BOOLEAN) && is_bool($operand)){
			$valid = true;
		}
		
		if(!$valid && ($flag & self::$IS_ARRAY) && is_array($operand)){
			$allAreScalars = true;
			foreach ($operand as $elem){
				if(!is_scalar($elem)){
					$allAreScalars = false;
				}
			}
			if($allAreScalars){
				$valid = true;
			}
		}
		
		if(!$valid){
			throw new InvalidInputException('Invalid Operand');
		}
	}
	
	private function expectedOperandsLength($operands,$length,$minimum){
		if(isset($minimum)){
			if(count($operands) < $length){
				throw new InvalidInputException('Invalid number of Operands');
			}
		}elseif(count($operands) != $length){
			throw new InvalidInputException('Invalid number of Operands');
		}
	}
	
	function asString($columnNameMapping){
		$connection = TransactionManager::getConnection();
		$operandsAsStrings = array();
		foreach ($this->operands as $operand){
			if($operand instanceof Expression){
				$operand = "(".$operand->asString($columnNameMapping).")";
			}elseif($operand instanceof Field){
				if(isset($columnNameMapping[$operand->asString()])){
					$operand = $columnNameMapping[$operand->asString()];
				}else{
					throw new InvalidInputException('Invalid operand of type Field');
				}
			}elseif(is_string($operand)){
				$operand = $connection->encodeForSQLInjection($operand);
				if($this->operator == Model::OPERATOR_LIKE){
					$operand = "%$operand%";
				}
				$operand = "'$operand'";
			}elseif(is_numeric($operand) || is_bool($operand)){
				$operand = $operand;
			}elseif(is_array($operand)){
				if(count($operand) == 0){
					throw new InvalidInputException('the operand for IN operator can not be empty array');
				}
				$operandValues = array();
				foreach ($operand as $operandValue){
					$operandValues[] = "'".$connection->encodeForSQLInjection($operandValue)."'";
				}
				$operand = "(".implode(',', $operandValues).")";
			}else {
				throw new InvalidInputException('Unrecognized Operand');
			}
			$operandsAsStrings[] = $operand;
		}
		$operator = $this->operator;
		if($this->operator == Model::OPERATOR_IS_NULL){
			$operator = 'IS';
			$operandsAsStrings[] = 'NULL';
		}
		if($this->operator == Model::OPERATOR_IS_NOT_NULL){
			$operator = 'IS';
			$operandsAsStrings[] = 'NOT NULL';
		}
		if($this->operator == Model::OPERATOR_BETWEEN){
			$operandsAsStrings[1] = $operandsAsStrings[1]." AND ".$operandsAsStrings[2];
			unset($operandsAsStrings[2]);
		}
		return implode(" $operator ", $operandsAsStrings);
	}
	
}