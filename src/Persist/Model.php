<?php

namespace PhpPlatform\Persist;

use PhpPlatform\Config\Settings;
use PhpPlatform\Errors\Exceptions\Persistence\BadQueryException;
use PhpPlatform\Errors\Exceptions\Persistence\DataNotFoundException;
use PhpPlatform\Errors\Exceptions\Persistence\NoAccessException;
use PhpPlatform\Persist\Exception\InvalidForeignClassException;
use PhpPlatform\Persist\Exception\InvalidInputException;
use PhpPlatform\Persist\Exception\TriggerException;
use PhpPlatform\Persist\Connection\Connection;
use PhpPlatform\Annotations\Annotation;
use PhpPlatform\Session\Factory;

abstract class Model implements Constants{

    private static $_lastFindQuery = null;
    
    private static $_triggers = null;
    const TRIGGER_TYPE_PRE  = "PRE";
    const TRIGGER_TYPE_POST = "POST";
    const TRIGGER_EVENT_CREATE = "CREATE";
    const TRIGGER_EVENT_READ   = "READ";
    const TRIGGER_EVENT_UPDATE = "UPDATE";
    const TRIGGER_EVENT_DELETE = "DELETE";
    
    function __construct(){
    	
    	if(TransactionManager::getAttribute("isFromNewInstanceWithoutConstructor") === true){
    		return ;
    	}
		
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
            throw new BadQueryException(get_class($this)." can not be constructed with empty arguments");
        }
        
        $resultList = static::find($args);

        if(count($resultList) == 0){
        	$inputArgsStr = "";
        	foreach($args as $key=>$value){
        		if($inputArgsStr != ""){
        			$inputArgsStr .= ", ";
        		}
        		$inputArgsStr .= $key." = ".$value;
        	}
            throw new DataNotFoundException(get_class($this)." with ".$inputArgsStr." does not exist");
        }

        $classList = RelationalMappingUtil::getClassConfiguration(get_class($this));
        foreach($classList as $className=>$class){
            foreach(array_keys($class['fields']) as $fieldName){
                Reflection::setValue($className, $fieldName, $this,Reflection::getValue($className, $fieldName, $resultList[0]));
            }
        }
    }
    
    /**
     * @param string $className
     * @throws Exception
     * @return Model
     */
    private static function __newInstanceWithoutConstructor($className){
    	try{
    		TransactionManager::startTransaction();
    		TransactionManager::setAttribute("isFromNewInstanceWithoutConstructor",true);
    		$newInstance = Reflection::newInstanceArgs($className,array());
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return $newInstance;
    }
    
    /**
     * checks the access for this operation based on the annotations defined for this operation
     * @param string|Model $object on which access needs to be validated
     * @param string $errorMessage error message to be set as exception message if access is denied
     * @throws NoAccessException
     * @return boolean|string True or Valid where expression for Read access on Success otherwise false
     */
    private static function checkAccess($object,$errorMessage){
    	
    	if(is_string($object)){
    		$className = $object;
    		$object = null;
    	}else{
    		$className = get_class($object);
    	}
    	
    	if(TransactionManager::isSuperUser()){
    		return true;
    	}
    	
    	$hasAccess = false;
    	$hasAccessChecks = false;
    	try{
    		TransactionManager::startTransaction(null,true);
    		
    		$debugBacktraces = debug_backtrace(false);
    		$args = null;
    		$function = null;
    		foreach($debugBacktraces as $debugBacktrace){
    			if($debugBacktrace["class"] == $className){
    				$args = $debugBacktrace["args"];
    				$function = $debugBacktrace["function"];
    				break;
    			}
    		}
    		
    		$accessAnnotations = self::getAccessAnnotation($className, $function);
    		
    		if(is_array($accessAnnotations) && count($accessAnnotations) > 0){
    			$hasAccessChecks = true;
    			
    			//
    			// access annotations will be of the form 
    			// array(
    			//      "key1|value1",
    			//      "key2|value2",
    			//      ...
    			//      "function|functionName"
    			// )
    			// 
    			// Access is granted if session contains value for the specified key
    			// "function" is the special reserved key for which a function mentioned as value is called instead of checking for the value in session
    			
    			
    			// group access annotations by key
    			$accessMasks = array();
    			foreach ($accessAnnotations as $accessMask){
    				$accessMaskArr = preg_split('/\|/', $accessMask);
    				$accessMaskKey = $accessMaskArr[0];
    				$accessMaskValue = $accessMaskArr[1];
    				if(!array_key_exists($accessMaskKey, $accessMasks)){
    					$accessMaskValues = array();
    				}else{
    					$accessMaskValues = $accessMasks[$accessMaskKey];
    				}
    				$accessMaskValues[] = $accessMaskValue;
    				$accessMasks[$accessMaskKey] = $accessMaskValues;
    			}
    			
    			$session = Factory::getSession();
    			foreach ($accessMasks as $accessKey=>$accessValues){
    				if($accessKey == "function"){
    					continue;
    				}
    				$sessionValues = $session->get($accessKey);
    				
    				if(count(array_intersect($sessionValues, $accessValues)) > 0){
    					$hasAccess = true;
    					break;
    				}
    			}
    			
    			if(!$hasAccess && array_key_exists("function", $accessMasks)){
    				foreach ($accessMasks["function"] as $function){
    					$result = Reflection::invokeArgs($className, $function, $object, $args);
    					if(is_string($result)){
    						$hasAccess = $result;
    						break;
    					}
    					if(isset($result) && $result){
    						$hasAccess = true;
    						break;
    					}
    				}
    			}
    		}
    		
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	
    	if($hasAccessChecks){
    		if($hasAccess === false){
    			throw new NoAccessException($errorMessage);
    		}
    		return $hasAccess;
    	}
    	return true;
    }
    
    static private function getAccessAnnotation($class,$method){
    	if($class === false || $method === false){
    		return array();
    	}
    	$annotations = Annotation::getAnnotations($class,null,null,$method);
    	$annotations = $annotations["methods"][$method];
    	
    	if(array_key_exists("access", $annotations)){
    		$accessAnnotations = $annotations["access"];
    	}
    	if(is_string($accessAnnotations)){
    		$accessAnnotations = array($accessAnnotations);
    	}
    	if(in_array("inherit", $accessAnnotations)){
    		$parentClass = get_parent_class($class);
    		$parentAccessAnnotations = self::getAccessAnnotation($parentClass, $method);
    		unset($accessAnnotations["inherit"]);
    		$accessAnnotations = array_merge($accessAnnotations,$parentAccessAnnotations);
    	}
    	return $accessAnnotations;
    }
    
    /**
     * 
     * @param array $data
     * @throws NoAccessException
     * @throws InvalidInputException
     * @return Model
     */
    public static function create($data){
    	
    	$thisModelObject = null;
    	
    	try{
    		
    		TransactionManager::startTransaction();
    		
    		$connection = TransactionManager::getConnection();
    		
    		$calledClass = get_called_class();
    		
    		// check for Create Access
    		self::checkAccess($calledClass, "User don't have access to Create");
    		
    		// create an instance of calledClass
    		$thisModelObject = self::__newInstanceWithoutConstructor($calledClass);
    		
    		$classList = RelationalMappingUtil::getClassConfiguration($calledClass);
    		//getClassConfiguration will return the array of class config objects from child to parent , during creation values needs to be inserted from parent to child
    		$classList = array_reverse($classList);
    		foreach($classList as $className=>$class){
    			$columnNames = "";
    			$values = "";
    		
    			$fields = $class['fields'];
    			foreach($fields as $fieldName=>$field){
    				if(!RelationalMappingUtil::_isAutoIncrement($field) && !RelationalMappingUtil::_isForeignField($field)){
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
    							$value = $connection->formatDate($value);
    						}elseif(strtoupper($field['type']) == "DATETIME" ||strtoupper($field['type']) == "TIMESTAMP"){
    							$value = $connection->formatDate($value,true);
    						}elseif(strtoupper($field['type']) == "BOOLEAN"){
    							try{
    								$value = $connection->formatBoolean($value);
    							}catch (InvalidInputException $e){
    								throw new InvalidInputException("Expected boolean value for $fieldName");
    							}
    						}
    		
    						$values .= "'".addslashes($value)."'";
    					}
    				}
    			}
    		
    			self::runTrigger($className, self::TRIGGER_EVENT_CREATE, self::TRIGGER_TYPE_PRE, array($thisModelObject));
    			
    			$tableName = RelationalMappingUtil::getTableName($class, $thisModelObject);
    			$query = "INSERT INTO $tableName($columnNames) VALUES ($values)";
    		
    			$result = $connection->query($query);
    		
    			if($result === FALSE){
    				$errorMessage = "Error in creating $className \"".$connection->lastError()."\"";
    				throw new BadQueryException($errorMessage);
    			}
    		
    			if(null !== RelationalMappingUtil::getAutoIncrementKey($class)){
    				$autoIncrementValue = $connection->lastInsertedId();
    				Reflection::setValue($className, RelationalMappingUtil::getAutoIncrementKey($class), $thisModelObject, $autoIncrementValue);
    			}
    			self::runTrigger($className, self::TRIGGER_EVENT_CREATE, self::TRIGGER_TYPE_POST, array($thisModelObject));
    		}
    		
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
        $resultList = array();
        try{
        
        	TransactionManager::startTransaction();
        	$connection = TransactionManager::getConnection();
        	
	        $readAccessWhereClause = self::checkAccess($calledClassName, "User don't have access to Read");
	        
	        foreach ($classList as $className=>$class){
	        	self::runTrigger($className, self::TRIGGER_EVENT_READ, self::TRIGGER_TYPE_PRE, array(array("filters"=>$filters,"sort"=>$sort,"pagination"=>$pagination, "where"=>$where)));
	        }
	
	        
	        $Clauses = self::generateClauses($connection,$classList, $filters, $sort, $where, $readAccessWhereClause);
	        
	        if($Clauses === false){
	        	throw new DataNotFoundException("generateClauses returned false");
	        }
	
	        $selectClause  = $Clauses["selectClause"];
	        $fromClause    = $Clauses["fromClause"];
	        $whereClause   = $Clauses["whereClause"];
	        $groupByClause = $Clauses["groupByClause"];
	        $orderByClause = $Clauses["orderByClause"];
	        
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
                	$limit = "LIMIT ".$connection->encodeForSQLInjection($limit);
                }

            }
            
            $query = "SELECT $selectClause FROM $fromClause $whereClause $groupByClause $orderByClause $limit";

            $result = $connection->query($query);

            if(!$result){
                throw new BadQueryException("Select Failed : "+$connection->lastError());
            }

            self::$_lastFindQuery = array("selectClause" =>$selectClause,
                "fromClause"=>$fromClause,
                "whereClause"=>$whereClause,
                "groupByClause"=>$groupByClause,
                "orderByClause"=>$orderByClause,
                "limit"=>$limit);

            $row = $result->fetch_assoc();

            while($row != null){
            	$resultObj = self::__newInstanceWithoutConstructor($calledClassName);
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
    
    /**
     * 
     * @param Connection $connection
     * @param array $classList
     * @param array $filters
     * @param array $sort
     * @param array $where
     * @param string $readAccessWhereClause
     * @throws InvalidForeignClassException
     * @throws InvalidInputException
     * @return string[][]
     */
    private static function generateClauses($connection, &$classList,$filters,$sort,&$where,&$readAccessWhereClause){
    	$fromClause = "";
    	$whereClause = "";
    	$selectClause = "";
    	$groupByClause = "";
    	$orderByClause = "";
    	
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
    		$fromClause .= RelationalMappingUtil::getTableName($class, $className)." ".$prefix;
    	
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
    					$foreignClassTableName = RelationalMappingUtil::getTableName($foreignClassConf, $foreignClassName);
    					$fromClause .= " LEFT JOIN ".$foreignClassTableName." ".$_prefix." ON ".$_prefix.".".$foreignClassConf['fields'][$foreignPrimaryField]['columnName']." = ".$class['prefix'].".".$field['columnName'];
    					$sourceColumnsForForeignFields[] = $field['columnName'];
    				}
    			}
    			$fieldSelect = $_prefix.".".$_columnName;
    	
    			$field['fieldSelect'] = $fieldSelect;
    	
    			$_fieldSelect = $fieldSelect;
    			if(strtoupper($field['type']) == "DATE"){
    				$_fieldSelect = "date_format($fieldSelect,'".$connection->outputDateFormat()."')";
    			}elseif(strtoupper($field['type']) == "DATETIME" || strtoupper($field['type']) == "TIMESTAMP"){
    				$_fieldSelect = "date_format($fieldSelect,'".$connection->outputDateTimeFormat()."')";
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
    					$value = $connection->encodeForSQLInjection($value);
    					$fieldWhereClause = "$_prefix.$_columnName $operator '%$value%'";
    				}else if($operator == self::OPERATOR_BETWEEN){
    					if(!is_array($value) || count($value) != 2){
    						throw new InvalidInputException("Invalid find argument for $fieldName");
    					}
    					$minValue = $connection->encodeForSQLInjection($value[0]);
    					$maxValue = $connection->encodeForSQLInjection($value[1]);
    	
    					$fieldWhereClause = "$_prefix.$_columnName $operator '$minValue' AND '$maxValue'";
    				}else if($operator == self::OPERATOR_IN){
    					if(!is_array($value)){
    						throw new InvalidInputException("Invalid find argument for $fieldName");
    					}
    					
    					if(count($value) == 0){
    						return false;
    					}
    	
    					$value = array_map(function($valueItem) use ($connection){
    						return $connection->encodeForSQLInjection($valueItem);
    					}, $value);
    	
    					$valueStr = implode("','",$value);
    					$valueStr = "('".$valueStr."')";
    					$fieldWhereClause = "$_prefix.$_columnName $operator $valueStr";
    				}else {
    					if($value === null){
    						$operator = "is";
    						$value = "null";
    					}else{
    						$value = $connection->encodeForSQLInjection($value);
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
    	 
    	return $Clauses;
    	
    }
    
    

    public static function getLastTotalRecords($pageSize = null){
        try{
            TransactionManager::startTransaction();
            $connection = TransactionManager::getConnection();
            $fromClause = self::$_lastFindQuery['fromClause'];
            $whereClause = self::$_lastFindQuery['whereClause'];
            $groupByClause = self::$_lastFindQuery['groupByClause'];
            $orderByClause = self::$_lastFindQuery['orderByClause'];

            $countResults = $connection->query("SELECT COUNT(*) FROM $fromClause $whereClause $groupByClause $orderByClause");
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
                throw new DataNotFoundException("Error in getting total records of last find");
            }
            TransactionManager::commitTransaction();
            return $returnCount;
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
    }

    protected function delete(){
        try{
            TransactionManager::startTransaction();
            $conection = TransactionManager::getConnection();

            $classList = RelationalMappingUtil::getClassConfiguration(get_class($this));

            // check for Delete Access
            self::checkAccess($this, "User don't have access to Delete");

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
                            if($fieldValue == null){
                            	$fieldValue = 'IS NULL';
                            }else{
                            	$fieldValue = " = '$fieldValue'";
                            }

                            if($whereClause != ""){
                                $whereClause .= " AND ";
                            }
                            $whereClause .= ($field['columnName']." $fieldValue");
                        }
                    }
                }


                $tableName = RelationalMappingUtil::getTableName($class, $this);
                $query = "DELETE FROM $tableName WHERE ".$whereClause;

                self::runTrigger($className, self::TRIGGER_EVENT_DELETE, self::TRIGGER_TYPE_PRE, array($this));
                $result = $conection->query($query);

                if($result === FALSE){
                    $errorMessage = "Error in deleting ".get_class($this).":$className \"".$conection->lastError()."\"";
                    throw new BadQueryException($errorMessage);
                }
                self::runTrigger($className, self::TRIGGER_EVENT_DELETE, self::TRIGGER_TYPE_POST, array($this));
                
            }

            TransactionManager::commitTransaction();
        }catch (\Exception $exp){
            TransactionManager::abortTransaction();
            throw $exp;
        }
    }

    /**
     * 
     * @param string $name
     * @param mixed $value
     * @return Model
     */
    protected function setAttribute($name,$value){
        $args = array();
        $args[$name] = $value;

        return $this->setAttributes($args);
    }

    /**
     * @param array $args
     * @return Model
     */
    protected function setAttributes($args){
        try{
            TransactionManager::startTransaction();
            $connection = TransactionManager::getConnection();

            $modifiedClasses = array();

            $classList = RelationalMappingUtil::getClassConfiguration(get_class($this));

            // check for Update Access
            self::checkAccess($this, "User don't have access to Update");
            
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
                                $value = $connection->formatDate($value);
                            }elseif(strtoupper($field['type']) == "DATETIME" || strtoupper($field['type']) == "TIMESTAMP"){
                                $value = $connection->formatDate($value,true);
                            }elseif(strtoupper($field['type']) == "BOOLEAN"){
                                try{
                                    $value = $connection->formatBoolean($value);
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
                                
                                $foreignClassTableName = RelationalMappingUtil::getTableName($foreignClassConf, $foreignClassName);

                                $result = $connection->query("SELECT ".$foreignPrimaryField['columnName']." FROM ".$foreignClassTableName." WHERE ".$foreignClassConf['fields'][$foreignFieldName]['columnName']." = '".$value."'");

                                if($result === FALSE){
                                    $errorMessage = "Error in updating $className \"".$connection->error."\"";
                                    throw new BadQueryException($errorMessage);
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

                $tableName = RelationalMappingUtil::getTableName($class, $this);
                $query = "UPDATE $tableName SET $setClause WHERE $recordIdentifier";

                self::runTrigger($className, self::TRIGGER_EVENT_UPDATE, self::TRIGGER_TYPE_PRE, array($this,$modifiedFields));
                $result = $connection->query($query);

                if($result === FALSE){
                    $errorMessage = "Error in updating ".get_class($this).":$className \"".$connection->lastError()."\"";
                    throw new BadQueryException($errorMessage);
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
        
        return $this;

    }

    protected function getAttribute($name){
        $args = array();
        $args[] = $name;

        $attrValues = $this->getAttributes($args);
        return $attrValues[$name];
    }

    protected function getAttributes($args){
        try{

            $classList = RelationalMappingUtil::getClassConfiguration(get_class($this));

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
    				
    				$triggerClassObj = Reflection::newInstanceArgs($triggerClass);
    				Reflection::invokeArgs($triggerClass, $triggerMethod, $triggerClassObj,$parameters);
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