<?php

/**
 * User: Raaghu
 * Date: 02-08-2015
 * Time: PM 10:19
 */

namespace PhpPlatform\Tests\Persist;

use PDO;
use PHPUnit_Extensions_Database_TestCase as DBUnitTestcase;
use PhpPlatform\Mock\Config\MockSettings;
use PhpPlatform\Config\SettingsCache;
use PhpPlatform\Persist\RelationalMappingCache;
use PhpPlatform\JSONCache\Cache;

abstract class ModelTest extends DBUnitTestcase{

    private $_dataSet = null;
    private static $_databaseName = "";
    protected static $_pdo = null;
    private static $_connectionParams = null;
    
    protected static function getSchemaFiles(){
    	return array(dirname(__FILE__).'/persisttest.ddl.sql');
    }
    
    public static function getDataSetFiles(){
    	return array(dirname(__FILE__).'/persisttest_seed.xml');
    }
    
    public static function setUpBeforeClass(){
    	self::$_databaseName = "db".preg_replace('/[^0-9]/', '', microtime());
    	
    	// get connection parameters
    	$host     = getenv("MYSQL_HOST")    ?getenv("MYSQL_HOST")    :(defined('MYSQL_HOST')    ?MYSQL_HOST    :'localhost');
    	$port     = getenv("MYSQL_PORT")    ?getenv("MYSQL_PORT")    :(defined('MYSQL_PORT')    ?MYSQL_PORT    :'3306');
    	$username = getenv("MYSQL_USERNAME")?getenv("MYSQL_USERNAME"):(defined('MYSQL_USERNAME')?MYSQL_USERNAME:'root');
    	$password = getenv("MYSQL_PASSWORD")?getenv("MYSQL_PASSWORD"):(defined('MYSQL_PASSWORD')?MYSQL_PASSWORD:'');
    	self::$_connectionParams = array(
    		"host"=>$host,
    		"port"=>$port,
    		"username"=>$username,
    		"password"=>$password
    	);
    	
    	
    	// create pdo without database
    	$_pdo = new PDO('mysql:host='.$host.';port='.$port, $username, $password);
    	
    	// create database
    	$result = $_pdo->query("CREATE DATABASE ".self::$_databaseName.";");
    	if($result === false){
    		print_r($_pdo->errorInfo());
    		return ;
    	}
    	unset($result);
    	unset($_pdo);
    	
    	// create pdo for new databse
    	self::$_pdo = new PDO('mysql:host='.$host.';port='.$port.';dbname='.self::$_databaseName, $username,$password);
    	
    	// reset configurations
    	Cache::getInstance()->reset();
    	SettingsCache::getInstance()->reset();
    	RelationalMappingCache::getInstance()->reset();
    	MockSettings::setSettings('php-platform/persist', "dbHost", $host);
    	MockSettings::setSettings('php-platform/persist', "dbPort", $port);
    	MockSettings::setSettings('php-platform/persist', "dbName", self::$_databaseName);
    	MockSettings::setSettings('php-platform/persist', "dbUsername", $username);
    	MockSettings::setSettings('php-platform/persist', "dbPassword", $password);
    	MockSettings::setSettings('php-platform/persist', "outputDateTimeFormat", "%Y-%m-%d %H:%i:%S");
    	self::setTriggers(array());
    	
    	// create schema in database
    	foreach (static::getSchemaFiles() as $schemaSqlFile){
    		$sql = file_get_contents($schemaSqlFile);
    		$result = self::$_pdo->exec($sql);
    		if($result === false){
    			print_r(self::$_pdo->errorInfo());
    			return ;
    		}
    	}
    	
    }
    
    public static function tearDownAfterClass(){
    	// create pdo without database
    	$_pdo = new PDO('mysql:host='.self::$_connectionParams['host'].';port='.self::$_connectionParams['port'], self::$_connectionParams['username'],self::$_connectionParams['password']);
    	 
    	// drop database
    	$result = $_pdo->query("DROP DATABASE ".self::$_databaseName.";");
    	if($result === false){
    		print_r($_pdo->errorInfo());
    		return ;
    	}
    	unset($result);
    	unset($_pdo);
    }

    public function getConnection(){
        return $this->createDefaultDBConnection(self::$_pdo);
    }

	public function getDataset($seedxml = null){
		if(isset($seedxml)){
			$seedContent = file_get_contents($seedxml);
		}else{
			$seedContent = "";
			foreach (static::getDataSetFiles() as $dataSetFile){
				$seedContent .= file_get_contents($dataSetFile);
			}
		}
		
		$seedContent = preg_replace('/\<database[\s]*name[\s]*=[\s]*"[a-zA-Z0-9]*"[\s]*\>/','<database name="'.self::$_databaseName.'">',$seedContent);

		$tmpFile = tempnam(sys_get_temp_dir(),self::$_databaseName);
		file_put_contents($tmpFile,$seedContent);

		return $this->createMySQLXMLDataSet($tmpFile);
	}
	
	public function getDatasetValue($table,$row,$column = null){
		$value = $this->getDataset()->getTable($table)->getRow($row);
		if(isset($column)){
			$value = $value[$column];
		}
		return $value;
	}
    

    function assertPrimaryIds($expected,$actual,$className,$message = null){
        $i = 0;

        $classReflection = new \ReflectionClass($className);
        $fPrimaryIdReflection = $classReflection->getProperty("fPrimaryId");
        $fPrimaryIdReflection->setAccessible(true);
        foreach($actual as $findResult){
            $actualPrimaryId = $fPrimaryIdReflection->getValue($findResult);
            $this->assertEquals($expected[$i],$actualPrimaryId,$message);
            $i++;
        }
    }

    public function assertSelect($expected,$query,$message = null){
        $dataSet = new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet($expected);
        $tableNames = $dataSet->getTableNames();
        $tableName = $tableNames[0];

        $queryTable = $this->getConnection()->createQueryTable(
            $tableName, $query
        );

        $expectedTable = $dataSet->getTable($tableName);
        $this->assertTablesEqual($expectedTable, $queryTable,$message);
    }
    
    
    public static function setTriggers($triggers){
    	MockSettings::setSettings('php-platform/persist',"triggers",$triggers);
    	
    	// force reset the $_triggers property on icircle\icoodb\Model 
    	$_triggers = new \ReflectionProperty('PhpPlatform\Persist\Model', '_triggers');
    	$_triggers->setAccessible(true);
    	$_triggers->setValue(null,null);
    	
    	define('TRIGGER_TEST_LOG', 'TRIGGER_TEST_LOG');
    	
    }

}