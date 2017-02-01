<?php

namespace PhpPlatform\Persist;

use PhpPlatform\Errors\Exceptions\Persistence\DataNotFoundException;
use PhpPlatform\Errors\Exceptions\Persistence\PersistenceException;
use PhpPlatform\Persist\Exception\InvalidForeignClassException;
use PhpPlatform\Persist\Exception\InvalidInputException;
use PhpPlatform\Errors\Exceptions\Persistence\NoAccessException;
use PhpPlatform\Persist\Exception\ObjectStateException;
use PhpPlatform\Persist\Exception\TriggerException;
use PhpPlatform\Config\Settings;
use PhpPlatform\Errors\Exceptions\Persistence\BadQueryException;

abstract class Model implements Constants{
    protected $isObjectInitialised = false;

    private $classConfigList = null;
    private static $_lastFindQuery = null;
    
    private static $_triggers = null;
    const TRIGGER_TYPE_PRE  = "PRE";
    const TRIGGER_TYPE_POST = "POST";
    const TRIGGER_EVENT_CREATE = "CREATE";
    const TRIGGER_EVENT_READ   = "READ";
    const TRIGGER_EVENT_UPDATE = "UPDATE";
    const TRIGGER_EVENT_DELETE = "DELETE";
    
    function __construct(){
		
		$args = array();
        
		$classList = RelationalMappingUtil::getClassConfiguration($this);
        foreach($classList as $className=>$class){
            foreach(array_keys($class['fields']) as $fieldName){
                $fieldValue = Reflection::getValue($className, $fieldName, $this);
				if($fieldValue !== null){
					$args[$fieldName] = $fieldValue;
				}
            }
        }
		
        if(count($args) == 0){
            return;
        }
        
        $resultList = static::find($args);

        $inputArgsStr = "";
        foreach($args as $key=>$value){
            if($inputArgsStr != ""){
                $inputArgsStr .= ", ";
            }
            $inputArgsStr .= $key." = ".$value;
        }

        if(count($resultList) == 0){
            throw new DataNotFoundException(get_class($this)." with ".$inputArgsStr." does not exist");
        }

        $classList = $this->getClassConfigList();
        foreach($classList as $className=>$class){
            foreach(array_keys($class['fields']) as $fieldName){
                Reflection::setValue($className, $fieldName, $this,Reflection::getValue($className, $fieldName, $resultList[0]));
            }
        }
        $this->isObjectInitialised = true;
    }
    
    
    private static function checkAccess($object,$accessType,$errorMessage){
    	
    	if(is_string($object)){
    		$className = $object;
    		$object = null;
    	}else{
    		$className = get_class($object);
    	}
    	
    	$classList = RelationalMappingUtil::getClassConfiguration($className);
    	
    	if(!TransactionManager::isSuperUser()){
    		foreach(array_keys($classList) as $_className){
    			try{
    				$result = Reflection::invokeArgs($_className, $accessType, $object);
    				if($result === false){
    					throw new NoAccessException($errorMessage);
    				}
    				return $result;
    			}catch (\ReflectionException $re){
    				// do nothing
    			}
    		}
    	}
    	return true;
    }
    
    
    /**
     * 
     * @param array $data
     * @throws NoAccessException
     * @throws InvalidInputException
     * @throws PersistenceException
     * @return Model
     */
    public static function create($data){
    	
    	$thisModelObject = null;
    	
    	try{
    		
    		TransactionManager::startTransaction();
    		
    		$dbs = TransactionManager::getConnection();
    		
    		$calledClass = get_called_class();
    		
    		// check for Create Access
    		self::checkAccess($calledClass, "CreateAccess", "User don't have access to Create");
    		
    		// create an instance of calledClass
    		$thisModelObject = Reflection::newInstanceArgs($calledClass);
    		
    		$classList = RelationalMappingUtil::getClassConfiguration($calledClass);
    		//getClassConfigList will return the array of class config objects from child to parent , during creation values needs to be inserted from parent to child
    		$classList = array_reverse($classList);
    		foreach($classList as $className=>$class){
    			$columnNames = "";
    			$values = "";
    		
    			$fields = $class['fields'];
    			foreach($fields as $fieldName=>$field){
    				if(!RelationalMappingUtil::_isAutoIncrement($field) && (RelationalMappingUtil::_isReference($field) || RelationalMappingUtil::_isSet($field))){
    					if($columnNames != ""){
    						$columnNames .=",";
    					}
    					if($values != ""){
    						$values .=",";
    					}
    		
    					$columnNames .= $field['columnName'];
    		
    					$value = null;
    					if(RelationalMappingUtil::_isReference($field)){
    						$parentClassName = get_parent_class($className);
    						$parentClass = $classList[$parentClassName];
    						$value = Reflection::getValue($parentClassName, RelationalMappingUtil::getPrimaryKey($parentClass), $thisModelObject);
    					}else{
    						$value = $data[$fieldName];
    					}
    					
    					Reflection::setValue($className, $fieldName, $thisModelObject, $value);
    				
    					if($value === null){
    						$values .= 'NULL';
    					}else{
    						if(strtoupper($field['type']) == "DATE"){
    							$value = MySql::getMysqlDate($value);
    						}elseif(strtoupper($field['type']) == "DATETIME" ||strtoupper($field['type']) == "TIMESTAMP"){
    							$value = MySql::getMysqlDate($value,true);
    						}elseif(strtoupper($field['type']) == "BOOLEAN"){
    							try{
    								$value = MySql::getMysqlBooleanValue($value);
    							}catch (InvalidInputException $e){
    								throw new InvalidInputException("Expected boolean value for $fieldName");
    							}
    						}
    		
    						$values .= "'".addslashes($value)."'";
    					}
    				}
    			}
    		
    			self::runTrigger($className, self::TRIGGER_EVENT_CREATE, self::TRIGGER_TYPE_PRE, array($thisModelObject));
    			
    			$tableName = $class['tableName'];
    			$query = "INSERT INTO $tableName($columnNames) VALUES ($values)";
    		
    			$result = $dbs->query($query);
    		
    			if($result === FALSE){
    				$errorMessage = "Error in creating $className \"".$dbs->error."\"";
    				throw new BadQueryException($errorMessage);
    			}
    		
    			if(null !== RelationalMappingUtil::getAutoIncrementKey($class)){
    				$autoIncrementValue = $dbs->insert_id;
    				Reflection::setValue($className, RelationalMappingUtil::getAutoIncrementKey($class), $thisModelObject, $autoIncrementValue);
    			}
    			self::runTrigger($className, self::TRIGGER_EVENT_CREATE, self::TRIGGER_TYPE_POST, array($thisModelObject));
    		}
    		Reflection::setValue(get_class(), 'isObjectInitialised', $thisModelObject, true);
    		
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return $thisModelObject;
    }

    /**
     * This method finds the data from database and constructs appropriate php daos
     *
     * @param $filters array of fields to match for
     * @param null $sort array for sorting
     * @param null $pagination array(pageNumber,pageSize)
     * @param null $where complex SQL where expression , column names should be written in {className.fieldName} format ,
     *             !important - contents of $where should be SQL injection free
     * @return array
     * @throws \Exception
     * 
     * @todo Add select option to select required values, in one loop, or a callback method to work with found objects
     */

    public static function find($filters,$sort = null,$pagination = null, $where = null){

        self::$_lastFindQuery = null;
        $calledClassName = get_called_class();

        $classList = RelationalMappingUtil::getClassConfiguration($calledClassName);
        
        $readAccessWhereClause = self::checkAccess($calledClassName, "ReadAccess", "User don't have access to Read");
        
        foreach ($classList as $className=>$class){
        	self::runTrigger($className, self::TRIGGER_EVENT_READ, self::TRIGGER_TYPE_PRE, array(array("filters"=>$filters,"sort"=>$sort,"pagination"=>$pagination, "where"=>$where)));
        }

        $Clauses = self::generateClauses($classList, $filters, $sort, $where, $readAccessWhereClause);
        
        if($Clauses === false){
        	return array();
        }

        $selectClause  = $Clauses["selectClause"];
        $fromClause    = $Clauses["fromClause"];
        $whereClause   = $Clauses["whereClause"];
        $groupByClause = $Clauses["groupByClause"];
        $orderByClause = $Clauses["orderByClause"];
        $values        = $Clauses["values"];
        
        $i = 0;
        
        $classCount = count($classList);
        foreach($classList as $className=>$class){
            //skip the last class
            if($i < $classCount-1){
                if($whereClause != ""){
                    $whereClause .=" AND ";
                }
                $parentClass = $classList[get_parent_class($className)];
                $whereClause .= $class['prefix'].".".$class['fields'][RelationalMappingUtil::getReferenceKey($class)]['columnName']." = ".
                    $parentClass['prefix'].".".$parentClass['fields'][RelationalMappingUtil::getPrimaryKey($parentClass)]['columnName'];
            }
            $i++;
        }

        $resultList = array();
        try{

            TransactionManager::startTransaction();
            $dbs = TransactionManager::getConnection();

            if(isset($where) && $where != ""){
                if($whereClause != ""){
                    $whereClause .= " AND ";
                }
                $whereClause .= "($where)";
            }

            if(isset($readAccessWhereClause) && $readAccessWhereClause != ""){
                if($whereClause != ""){
                    $whereClause .= " AND ";
                }
                $whereClause .= "($readAccessWhereClause)";
            }

            if($whereClause != ""){
                $whereClause = "WHERE ".$whereClause;
            }

            

            $limit = "";
            if(is_array($pagination)){

                if(array_key_exists("start",$pagination) && array_key_exists("pageSize",$pagination)){
                    $limit = $pagination["start"].",".$pagination["pageSize"];
                }elseif(array_key_exists("pageNumber",$pagination) && array_key_exists("pageSize",$pagination)){
                    $limit = (($pagination["pageNumber"]-1)*$pagination["pageSize"]).",".$pagination["pageSize"];
                }elseif(array_key_exists("start",$pagination) && array_key_exists("end",$pagination)){
                    $limit = $pagination["start"].",".($pagination["end"]-$pagination["start"]);
                }

                if($limit != ""){
                	$limit = "LIMIT ".$dbs->real_escape_string($limit);
                }

            }
            
            // escape for sql injection
            foreach ($values as $i=>$value){
            	$whereClause = str_replace('$'.($i+1), $dbs->real_escape_string($value), $whereClause);
            }

            $query = "SELECT $selectClause FROM $fromClause $whereClause $groupByClause $orderByClause $limit";

            $result = $dbs->query($query);

            if(!$result){
                throw new DataNotFoundException("Empty Results");
            }

            self::$_lastFindQuery = array("selectClause" =>$selectClause,
                "fromClause"=>$fromClause,
                "whereClause"=>$whereClause,
                "groupByClause"=>$groupByClause,
                "orderByClause"=>$orderByClause,
                "limit"=>$limit);

            $row = $result->fetch_assoc();

            while($row != null){
                $resultObj = Reflection::newInstanceArgs($calledClassName);
                
                foreach($classList as $className=>$class){
                	$fields = $class['fields'];
                    foreach($fields as $fieldName=>$field){
                        $fieldSelect = $field['fieldSelect'];
                        $value = $row[$fieldSelect];

                        if($field['group']){
                            $value = explode(" $$ ",$value);
                            $value = array_map(function($_v){
                                if($_v == "#NULL$"){
                                    return null;
                                }
                                return $_v;
                            },$value);
                        }

                        if(strtoupper($field['type']) === "BOOLEAN" ){
                            if($field['group']){
                                foreach($value as &$valueItem){
                                    if($valueItem == "1"){
                                        $valueItem = true;
                                    }else{
                                        $valueItem = false;
                                    }
                                }
                            }else{
                                if($value == "1"){
                                    $value = true;
                                }else{
                                    $value = false;
                                }
                            }
                        }
                        
                        Reflection::setValue($className, $fieldName, $resultObj, $value);
                    }
                }

                Reflection::setValue($className, 'isObjectInitialised', $resultObj, true);

                $resultList[] = $resultObj;
                $row = $result->fetch_assoc();
            }
            
            foreach($classList as $className=>$class){
            	self::runTrigger($className, self::TRIGGER_EVENT_READ, self::TRIGGER_TYPE_POST, array($resultList));
            }

            TransactionManager::commitTransaction();
        }catch (DataNotFoundException $e){
            TransactionManager::abortTransaction();
            // don't throw for data not found exception
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
        return $resultList;
    }
    
    private static function generateClauses(&$classList,$filters,$sort,&$where,$readAccessWhereClause){
    	$fromClause = "";
    	$whereClause = "";
    	$selectClause = "";
    	$groupByClause = "";
    	$orderByClause = "";
    	
    	$values = array();
    	$_processedSortArray = array();
    	
    	$allowedOperators = array( self::OPERATOR_LIKE,
    			self::OPERATOR_EQUAL,
    			self::OPERATOR_NOT_EQUAL,
    			self::OPERATOR_BETWEEN,
    			self::OPERATOR_GT,
    			self::OPERATOR_GTE,
    			self::OPERATOR_LT,
    			self::OPERATOR_LTE,
    			self::OPERATOR_IN
    	);
    	
    	foreach($classList as $className=>&$class){
    		$prefix = $class['prefix'];
    	
    		if($fromClause != ""){
    			$fromClause .= ", ";
    		}
    		$fromClause .= $class['tableName']." ".$prefix;
    	
    		$sourceColumnsForForeignFields = array();
    	
    		$fields = &$class['fields'];
    		foreach($fields as $fieldName=>&$field){
    			if($selectClause != ""){
    				$selectClause .= ", ";
    			}
    	
    			$_prefix = $prefix;
    			$_columnName = $field['columnName'];
    	
    			if(isset($field['foreignField'])){
    	
    				$foreignClassAndField = preg_split("/\-\>/",$field['foreignField']);
    				$foreignClassName = $foreignClassAndField[0];
    				$foreignFieldName = $foreignClassAndField[1];
    	
    				if(!class_exists($foreignClassName,true)){
    					throw new InvalidForeignClassException("Foreign Class ($foreignClassName) does not exists");
    				}
    	
    				$foreignClassConf = RelationalMappingCache::getInstance()->get($foreignClassName);
    	
    				$foreignPrimaryField = RelationalMappingUtil::getPrimaryKey($foreignClassConf);
    	
    				if($foreignPrimaryField == null){
    					throw new InvalidForeignClassException("foreign field ".$field['name']." doesn't have primary field on foreign Class ".$foreignClassName['name']);
    				}
    	
    				$foreignFieldForeignColumnName = $foreignClassConf['fields'][$foreignFieldName]['columnName'];
    	
    				$_prefix = $foreignClassConf['prefix']."_".$field['columnName'];
    				$_columnName = $foreignFieldForeignColumnName;
    				if(!in_array($field['columnName'],$sourceColumnsForForeignFields)){
    					$fromClause .= " LEFT JOIN ".$foreignClassConf['tableName']." ".$_prefix." ON ".$_prefix.".".$foreignClassConf['fields'][$foreignPrimaryField]['columnName']." = ".$class['prefix'].".".$field['columnName'];
    					$sourceColumnsForForeignFields[] = $field['columnName'];
    				}
    			}
    			$fieldSelect = $_prefix.".".$_columnName;
    	
    			$field['fieldSelect'] = $fieldSelect;
    	
    			$_fieldSelect = $fieldSelect;
    			if(strtoupper($field['type']) == "DATE"){
    				$_fieldSelect = "date_format($fieldSelect,'".MySql::getOutputDateFormat()."')";
    			}elseif(strtoupper($field['type']) == "DATETIME" || strtoupper($field['type']) == "TIMESTAMP"){
    				$_fieldSelect = "date_format($fieldSelect,'".MySql::getOutputDateTimeFormat()."')";
    			}
    	
    			if(isset($field['group']) && (strtoupper($field['group']) == "TRUE" || $field['group'] === true) ){
    				$field['group'] = true;
    				$_fieldSelect = "GROUP_CONCAT(IFNULL($_fieldSelect,'#NULL$') SEPARATOR ' $$ ')";
    			}else{
    				$field['group'] = false;
    			}
    	
    			$selectClause .= "$_fieldSelect AS '$fieldSelect'";
    	
    	
    			if(isset($filters[$fieldName]) && RelationalMappingUtil::_isGet($field)){
    	
    				if(!is_array($filters[$fieldName])){
    					$argObj = array();
    					$argObj[self::OPERATOR_EQUAL] = $filters[$fieldName];
    					$filters[$fieldName] = $argObj;
    				}
    	
    				// $filters[$fieldName] = array(Model::Operator => VALUE)
    				if(count($filters[$fieldName]) > 1){
    					throw new InvalidInputException("Invalid find argument for $fieldName");
    				}
    	
    				$argumentArrayKeys = array_keys($filters[$fieldName]);
    				$operator = $argumentArrayKeys[0];
    				if(!in_array($operator,$allowedOperators)){
    					throw new InvalidInputException("Invalid find argument for $fieldName");
    				}
    	
    				$value    = $filters[$fieldName][$operator];
    	
    				$fieldWhereClause = null;
    				if($operator == self::OPERATOR_LIKE){
    					$values[] = $value;
    					$value = '$'.count($values);
    					$fieldWhereClause = "$_prefix.$_columnName $operator '%$value%'";
    				}else if($operator == self::OPERATOR_BETWEEN){
    					if(!is_array($value) || count($value) != 2){
    						throw new InvalidInputException("Invalid find argument for $fieldName");
    					}
    					$minValue = $value[0];
    					$values[] = $minValue;
    					$minValue = '$'.count($values);
    	
    					$maxValue = $value[1];
    					$values[] = $maxValue;
    					$maxValue = '$'.count($values);
    	
    					$fieldWhereClause = "$_prefix.$_columnName $operator '$minValue' AND '$maxValue'";
    				}else if($operator == self::OPERATOR_IN){
    					if(!is_array($value)){
    						throw new InvalidInputException("Invalid find argument for $fieldName");
    					}
    					
    					if(count($value) == 0){
    						return false;
    					}
    	
    					$value = array_map(function($valueItem) use (&$values){
    						$values[] = $valueItem;
    						return '$'.count($values);
    					}, $value);
    	
    						$valueStr = implode("','",$value);
    						$valueStr = "('".$valueStr."')";
    						$fieldWhereClause = "$_prefix.$_columnName $operator $valueStr";
    				}else {
    					if($value === null){
    						$operator = "is";
    						$value = "null";
    					}else{
    						$values[] = $value;
    						$value = '$'.count($values);
    						$value = "'$value'";
    					}
    	
    					$fieldWhereClause = "$_prefix.$_columnName $operator $value";
    				}
    	
    				if($whereClause != ""){
    					$whereClause .= " AND ";
    				}
    	
    				$whereClause .= "(".$fieldWhereClause.")";
    				unset($filters[$fieldName]);
    			}
    	
    			if(isset($where)){
    				$where = str_replace("{"."$className.$fieldName"."}",$_prefix.".".$_columnName,$where);
    			}
    			$readAccessWhereClause = str_replace("{"."$className.$fieldName"."}",$_prefix.".".$_columnName,$readAccessWhereClause);
    	
    			if(isset($field['groupBy']) && (strtoupper($field['groupBy']) == "TRUE" || $field['groupBy'] === true )){
    				$groupByClause = "GROUP BY $_prefix.$_columnName";
    			}
    	
    			if(RelationalMappingUtil::_isGet($field) && is_array($sort) && array_key_exists($fieldName,$sort) &&
    					($sort[$fieldName] == self::SORTBY_ASC || $sort[$fieldName] == self::SORTBY_DESC) &&
    					!in_array($fieldName,$_processedSortArray) ){
    	
    						$sort[$fieldName] = "$_prefix.$_columnName ".$sort[$fieldName];
    						$_processedSortArray[] = $fieldName;
    			}
    		}
    	}
    	
    	if(is_array($sort) && count($sort) > 0 && count($sort) == count($_processedSortArray)){
    		$orderByClause = "ORDER BY ".implode(", ",$sort);
    	}
    	
    	$Clauses = array();
    	$Clauses["selectClause"]  = $selectClause;
    	$Clauses["fromClause"]    = $fromClause;
    	$Clauses["whereClause"]   = $whereClause;
    	$Clauses["groupByClause"] = $groupByClause;
    	$Clauses["orderByClause"] = $orderByClause;
    	$Clauses["values"]        = $values;
    	 
    	return $Clauses;
    	
    }
    
    

    public static function getLastTotalRecords($pageSize = null){
        try{
            TransactionManager::startTransaction();
            $dbs = TransactionManager::getConnection();
            $fromClause = self::$_lastFindQuery['fromClause'];
            $whereClause = self::$_lastFindQuery['whereClause'];
            $groupByClause = self::$_lastFindQuery['groupByClause'];
            $orderByClause = self::$_lastFindQuery['orderByClause'];

            $countResults = $dbs->query("SELECT COUNT(*) FROM $fromClause $whereClause $groupByClause $orderByClause");
            if($countResults){
                $countRow = $countResults->fetch_assoc();
                $totalRecords = $countRow["COUNT(*)"];

                if(isset($pageSize) && is_numeric($pageSize)){
                    $totalPages = ceil($totalRecords/$pageSize);
                    $returnCount = $totalPages;
                }else{
                    $returnCount = $totalRecords;
                }
            }else{
                throw new PersistenceException("Error in getting total records of last find");
            }
            TransactionManager::commitTransaction();
            return $returnCount;
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
    }

    protected function delete(){
        if(!$this->isObjectInitialised){
            throw new ObjectStateException("Object is not initialised");
        }
        try{
            TransactionManager::startTransaction();
            $dbs = TransactionManager::getConnection();

            $classList = $this->getClassConfigList();

            // check for Delete Access
            if(!TransactionManager::isSuperUser()){
                foreach($classList as $className=>$class){
                    $classReflection = new \ReflectionClass($className);
                    try{
                        $accessMethod = $classReflection->getMethod("DeleteAccess");
                        $accessMethod->setAccessible(true);
                        if(!$accessMethod->invoke($this)){
                            throw new NoAccessException("User don't have access to Delete");
                        }
                        break;
                    }catch (\ReflectionException $re){
                        // do nothing if method does not exist
                    }
                }
            }

            foreach($classList as $className=>$class){
                $reflectionClass = new \ReflectionClass($className);
                $fields = $class['fields'];
                foreach($fields as $fieldName=>$field){
                    if(isset($field['primary']) && (strtoupper($field['primary']) == "TRUE" || $field['primary'] === true) ){
                        $class['primayFieldName'] = $fieldName;
                        break;
                    }
                }

                $whereClause = "";
                if(isset($class['primayFieldName'])){
                    $reflectionProperty = $reflectionClass->getProperty($class['primayFieldName']);
                    $reflectionProperty->setAccessible(true);

                    $primaryField = $class['fields'][$class['primayFieldName']]['columnName'];
                    $primaryValue = $reflectionProperty->getValue($this);

                    $whereClause = "$primaryField = '$primaryValue'";

                }else{
                    foreach($fields as $fieldName=>$field){
                        if(isset($field['columnName']) && !isset($field['foreignField'])){
                            $reflectionProperty = $reflectionClass->getProperty($fieldName);
                            $reflectionProperty->setAccessible(true);
                            $fieldValue = $reflectionProperty->getValue($this);

                            if($whereClause != ""){
                                $whereClause .= " AND ";
                            }
                            $whereClause .= ($field['columnName']." = '$fieldValue'");
                        }
                    }
                }


                $tableName = $class['tableName'];
                $query = "DELETE FROM $tableName WHERE ".$whereClause;

                self::runTrigger($className, self::TRIGGER_EVENT_DELETE, self::TRIGGER_TYPE_PRE, array($this));
                $result = $dbs->query($query);

                if($result === FALSE){
                    $errorMessage = "Error in deleting ".get_class($this).":$className ";
                    throw new PersistenceException($errorMessage);
                }
                self::runTrigger($className, self::TRIGGER_EVENT_DELETE, self::TRIGGER_TYPE_POST, array($this));
                
            }
            
            $this->isObjectInitialised = false;

            TransactionManager::commitTransaction();
        }catch (\Exception $exp){
            TransactionManager::abortTransaction();
            throw $exp;
        }

        $this->isObjectInitialised = false;
    }

    protected function setAttribute($name,$value){
        $args = array();
        $args[$name] = $value;

        $this->setAttributes($args);
    }

    protected function setAttributes($args){
        if(!$this->isObjectInitialised){
            throw new ObjectStateException("Object is not initialised ");
        }
        try{
            TransactionManager::startTransaction();
            $dbs = TransactionManager::getConnection();

            $modifiedClasses = array();

            $classList = $this->getClassConfigList();

            // check for Update Access
            if(!TransactionManager::isSuperUser()){
                foreach($classList as $className=>$class){
                    $classReflection = new \ReflectionClass($className);
                    try{
                        $accessMethod = $classReflection->getMethod("UpdateAccess");
                        $accessMethod->setAccessible(true);
                        if(!$accessMethod->invoke($this)){
                            throw new NoAccessException("User don't have access to Update");
                        }
                        break;
                    }catch (\ReflectionException $re){
                        // do nothing if method does not exist
                    }
                }
            }

            foreach($classList as $className=>$class){
                $setClause = "";
                $modifiedFields = array();
                $reflectionClass = new \ReflectionClass($className);
                $fields = $class['fields'];
                foreach($fields as $fieldName=>$field){
                    if(isset($field['primary']) && (strtoupper($field['primary']) == "TRUE" || $field['primary'] === true) ){
                        $class['primayFieldName'] = $fieldName;
                    }
                    if(isset($field['set']) && (strtoupper($field['set']) == "TRUE" || $field['set'] === true )){
                        if(isset($args[$fieldName])){

                            $value = $args[$fieldName];
                            if(is_array($args[$fieldName])){
                                $value = $args[$fieldName]["value"];
                            }

                            $originalVal = $value;

                            if(strtoupper($field['type']) == "DATE"){
                                $value = MySql::getMysqlDate($value);
                            }elseif(strtoupper($field['type']) == "DATETIME" || strtoupper($field['type']) == "TIMESTAMP"){
                                $value = MySql::getMysqlDate($value,true);
                            }elseif(strtoupper($field['type']) == "BOOLEAN"){
                                try{
                                    $value = MySql::getMysqlBooleanValue($value);
                                }catch (InvalidInputException $e){
                                    throw new InvalidInputException("Expected boolean value for $fieldName");
                                }
                            }

                            if($field['foreignField']){
                                $foreignClassAndField = preg_split("/\-\>/",$field['foreignField']);
                                $foreignClassName = $foreignClassAndField[0];
                                $foreignFieldName = $foreignClassAndField[1];

                                $foreignClassConf = RelationalMappingCache::getInstance()->get($foreignClassName);

                                $foreignPrimaryField = (object)array();
                                foreach($foreignClassConf['fields'] as $foreignField){
                                    if(isset($foreignField['primary']) && (strtoupper($foreignField['primary']) == "TRUE" || $foreignField['primary'] === true) ){
                                        $foreignPrimaryField = $foreignField;
                                        break;
                                    }
                                }

                                $result = $dbs->query("SELECT ".$foreignPrimaryField['columnName']." FROM ".$foreignClassConf['tableName']." WHERE ".$foreignClassConf['fields'][$foreignFieldName]['columnName']." = '".$value."'");

                                if($result === FALSE){
                                    $errorMessage = "Error in updating $className \"".$dbs->error."\"";
                                    throw new PersistenceException($errorMessage);
                                }

                                $row = $result->fetch_assoc();
                                $value = $row[$foreignPrimaryField['columnName']];
                            }

                            $reflectionProperty = $reflectionClass->getProperty($fieldName);
                            $reflectionProperty->setAccessible(true);
                            $existingValue = $reflectionProperty->getValue($this);

                            if($existingValue != $originalVal){
                                if($setClause != ""){
                                    $setClause .= ", ";
                                }
                                if($value === null ){
                                    $setClause .= $field['columnName']." = NULL";
                                }else if($value === true ){
                                    $setClause .= $field['columnName']." = true";
                                }else if($value === false ){
                                    $setClause .= $field['columnName']." = false";
                                }else{
                                    $setClause .= $field['columnName']." = '".addslashes($value)."'";
                                }

                                $modifiedFields[$fieldName] = $originalVal;

                            }
                        }
                    }
                }

                if(count($modifiedFields)>0){
                    $modifiedClasses[$className] = $modifiedFields;
                }

                $reflectionClass = new \ReflectionClass($className);
                $recordIdentifier = "";
                if(isset($class['primayFieldName']) && $class['primayFieldName'] != ""){
                    $reflectionProperty = $reflectionClass->getProperty($class['primayFieldName']);
                    $reflectionProperty->setAccessible(true);
                    $keyValue = $reflectionProperty->getValue($this);
                    $recordIdentifier = $fields[$class['primayFieldName']]['columnName']." = '$keyValue'";
                }else{
                    foreach($fields as $fieldName=>$field){
                        if(isset($field['columnName']) && !isset($field['foreignField'])){
                            $reflectionProperty = $reflectionClass->getProperty($fieldName);
                            $reflectionProperty->setAccessible(true);
                            $fieldValue = $reflectionProperty->getValue($this);

                            if($recordIdentifier != ""){
                                $recordIdentifier .= " AND ";
                            }
                            $recordIdentifier .= ($field['columnName']." = '$fieldValue'");
                        }
                    }
                }

                if(trim($setClause) == ""){
                    continue;
                }

                $tableName = $class['tableName'];
                $query = "UPDATE $tableName SET $setClause WHERE $recordIdentifier";

                self::runTrigger($className, self::TRIGGER_EVENT_UPDATE, self::TRIGGER_TYPE_PRE, array($this,$modifiedFields));
                $result = $dbs->query($query);

                if($result === FALSE){
                    $errorMessage = "Error in updating ".get_class($this).":$className ";
                    throw new PersistenceException($errorMessage);
                }
                self::runTrigger($className, self::TRIGGER_EVENT_UPDATE, self::TRIGGER_TYPE_POST, array($this,$modifiedFields));
                
            }

            // updating the modified values to object instance
            //$thisClassName = get_class();

            foreach($modifiedClasses as $className=>$modifiedFields){
                $reflectionClass = new \ReflectionClass($className);
                foreach($modifiedFields as $fieldName=>$value){
                    $reflectionProperty = $reflectionClass->getProperty($fieldName);
                    $reflectionProperty->setAccessible(true);
                    $reflectionProperty->setValue($this,$value);
                }
            }

            TransactionManager::commitTransaction();
        }catch (\Exception $exp){
            TransactionManager::abortTransaction();
            throw $exp;
        }

    }

    protected function getAttribute($name){
        $args = array();
        $args[] = $name;

        $attrValues = $this->getAttributes($args);
        return $attrValues[$name];
    }

    protected function getAttributes($args){
        if(!$this->isObjectInitialised){
            throw new ObjectStateException("Object is not initialised ");
        }
        try{

            $classList = $this->getClassConfigList();

            if($args == "*"){
                $args = array();
                foreach($classList as $className=>$class){
                    $fields = $class['fields'];
                    foreach($fields as $fieldName=>$field){
                        if(!in_array($fieldName,$args)){
                            $args[] = $fieldName;
                        }
                    }
                }
            }

            $attributeValues = array();

            foreach($classList as $className=>$class){

                $reflectionClass = new \ReflectionClass($className);
                $fields = $class['fields'];
                foreach($fields as $fieldName=>$field){

                    if(isset($field['get']) && (strtoupper($field['get']) == "TRUE" || $field['get'] === true )){
                        if(in_array($fieldName,$args)){
                            $reflectionProperty = $reflectionClass->getProperty($fieldName);
                            $reflectionProperty->setAccessible(true);
                            $value = $reflectionProperty->getValue($this);
                            $attributeValues[$fieldName] = $value;
                            $index = array_search($fieldName,$args);
                            unset($args[$index]);
                        }
                    }
                }

            }
            return $attributeValues;
        }catch (\Exception $exp){
            throw $exp;
        }
    }

    /**
     * Method to check for Create Access
     * This method needs to be overridden by implementing Models
     *
     * @return boolean - True to allow creation , otherwise False
     */
    protected static function CreateAccess(){
        return true;
    }

    /**
     * Method to check for Read Access
     * This method needs to be overridden by implementing Models
     *
     * @return string - Where expression to use to find objects with read access,
     *         boolean - False if no Read allowed for the particular model
     */
    protected static function ReadAccess(){
        return true;
    }

    /**
     * Method to check for Update Access
     * This method needs to be overridden by implementing Models
     *
     * @return boolean - True to allow Update , otherwise False
     */
    protected function UpdateAccess(){
        return true;
    }

    /**
     * Method to check for Delete Access
     * This method needs to be overridden by implementing Models
     *
     * @return boolean - True to allow deletion , otherwise False
     */
    protected function DeleteAccess(){
        return true;
    }

    protected function getClassConfigList(){
        if(!isset($this->classConfigList)){
            $this->classConfigList = RelationalMappingUtil::getClassConfiguration(get_class($this));
        }
        return $this->classConfigList;
    }
    
    
    
    private static function runTrigger($className,$triggerEvent,$triggerType,$parameters){
    	if(self::$_triggers == null){
    		self::$_triggers = Settings::getSettings('php-platform/persist','triggers');
    	}
    	
    	if(isset(self::$_triggers[$className][$triggerEvent][$triggerType])){
    		$triggers = self::$_triggers[$className][$triggerEvent][$triggerType];
    		if(!is_array($triggers)){
    			throw new TriggerException("Invalid Trigger configuration : triggers[$className][$triggerEvent][$triggerType] should be an array");
    		}
    		foreach($triggers as $index => $trigger){
    			if(! isset($trigger["class"]) || ! isset($trigger["method"])){
    				throw new TriggerException("Invalid Trigger configuration : triggers[$className][$triggerEvent][$triggerType][$index] should have class and method");
    			}
    			$triggerClass = $trigger["class"];
    			$triggerMethod = $trigger["method"];
    			$triggerHashCode = "$className::$triggerEvent::$triggerType::$triggerClass::$triggerMethod";
    			try{
    				if(isset($_ENV[$triggerHashCode]) && $_ENV[$triggerHashCode] == true){
    					throw new TriggerException("Loop in the Trigger, ($className::$triggerEvent::$triggerType::$triggerClass::$triggerMethod) is repeated");
    				}
    				$_ENV[$triggerHashCode] = true;
    				
    				$reflectionClass = new \ReflectionClass($triggerClass);
    				$triggerClassObj = $reflectionClass->newInstanceArgs(array());
    				
    				$triggerReflectionMethod = $reflectionClass->getMethod($triggerMethod);
    				$triggerReflectionMethod->invokeArgs($triggerClassObj, $parameters);
    				unset($_ENV[$triggerHashCode]);
    			}catch(\Exception $e){
    				unset($_ENV[$triggerHashCode]);
    				$triggerException = new TriggerException("Trigger([$className][$triggerEvent][$triggerType] => $triggerClass::$triggerMethod) Failed : ".$e->getMessage());
    				if(! isset($trigger["ignoreErrors"]) || $trigger["ignoreErrors"] === false){
    					throw $triggerException;
    				}
    			}
    		}
    	}
    }


}


?>