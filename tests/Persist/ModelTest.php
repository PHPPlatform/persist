<?php

/**
 * User: Raaghu
 * Date: 02-08-2015
 * Time: PM 10:19
 */

namespace PhpPlatform\Tests\Persist;

use PhpPlatform\Tests\PersistUnit\ModelTest as PersistUnitTest;
use PhpPlatform\Persist\RelationalMappingCache;
use PhpPlatform\Errors\ErrorHandler;

abstract class ModelTest extends PersistUnitTest{
    
    protected static function getSchemaFiles(){
    	$schemaFiles = parent::getDataSetFiles();
    	array_push($schemaFiles, dirname(__FILE__).'/persisttest.ddl.sql');
    	return $schemaFiles;
    }
    
    protected static function getDataSetFiles(){
    	$dataSetFiles = parent::getDataSetFiles();
    	array_push($dataSetFiles, dirname(__FILE__).'/persisttest_seed.xml');
    	return $dataSetFiles;
    }
    
    protected static function getCaches(){
    	$caches = parent::getCaches();
    	array_push($caches, RelationalMappingCache::getInstance());
    	return $caches;
    }
    
    static function setUpBeforeClass(){
    	parent::setUpBeforeClass();
    }
    
}