<?php
/**
 * User: Raaghu
 * Date: 23-08-2015
 * Time: PM 09:38
 */

namespace PhpPlatform\Tests\Persist;

use PhpPlatform\Tests\Persist\Dao\TChild1;
use PhpPlatform\Tests\Persist\Dao\TChild2;
use PhpPlatform\Tests\Persist\Dao\TNormal1;
use PhpPlatform\Tests\Persist\Dao\TNormal2;
use PhpPlatform\Tests\Persist\Dao\TParent;
use PhpPlatform\Errors\Exceptions\Persistence\NoAccessException;
use PhpPlatform\Errors\Exceptions\Persistence\BadQueryException;
use PhpPlatform\Errors\Exceptions\Persistence\DataNotFoundException;

class ModelConstructorTest extends ModelTest{
    

    public function testConstruct(){

        // test for no exceptions while constructing
        $isException = false;
        try{
            new TNormal1(1);
        }catch (\Exception $e){
            echo $e;
            $isException = true;
        }
        $this->assertTrue(!$isException);

        $TNormal2Reflection = new \ReflectionClass('PhpPlatform\Tests\Persist\Dao\TNormal2');
        
        // test for no argument constructor
        $isException = false;
        try{
            $tNormal2Obj = new TNormal2();
        }catch (BadQueryException $e){
        	$this->assertEquals('PhpPlatform\Tests\Persist\Dao\TNormal2 can not be constructed with empty arguments',$e->getMessage());
        	$isException = true;
        }
        $this->assertTrue($isException);
        
        // test for constructor with argument
        $tNormal2Obj = new TNormal2(1);

        $fPrimaryIdReflection = $TNormal2Reflection->getProperty("fPrimaryId");
        $fPrimaryIdReflection->setAccessible(true);
        $this->assertEquals("1",$fPrimaryIdReflection->getValue($tNormal2Obj));

        $fVarcharReflection = $TNormal2Reflection->getProperty("fVarchar");
        $fVarcharReflection->setAccessible(true);

        $this->assertEquals($this->getDatasetValue("t_normal2",0,'F_VARCHAR'),$fVarcharReflection->getValue($tNormal2Obj));
        
        
        // test for constructor for non-existing data
        $isException = false;
        try{
        	$tNormal2Obj = new TNormal2(10,true);
        }catch (DataNotFoundException $e){
        	$this->assertEquals('PhpPlatform\Tests\Persist\Dao\TNormal2 with fPrimaryId = 10, fBoolean = 1 does not exist',$e->getMessage());
        	$isException = true;
        }
        $this->assertTrue($isException);

        // test for constructor with argument - for inherited Classes
        $TChild1Reflection = new \ReflectionClass('PhpPlatform\Tests\Persist\Dao\TChild1');

        $isException = false;
        try{
            $tChild1Obj = new TChild1();
        }catch (BadQueryException $e){
        	$this->assertEquals('PhpPlatform\Tests\Persist\Dao\TChild1 can not be constructed with empty arguments',$e->getMessage());
        	$isException = true;
        }
        $this->assertTrue($isException);

        $tChild1Obj = new TChild1(1);

        $fPrimaryIdReflection = $TChild1Reflection->getProperty("fPrimaryId");
        $fPrimaryIdReflection->setAccessible(true);
        $this->assertEquals($this->getDatasetValue("t_child1",0,'F_PRIMARY_ID'),$fPrimaryIdReflection->getValue($tChild1Obj));

        $fTimestampReflection = $TChild1Reflection->getProperty("fTimestamp");
        $fTimestampReflection->setAccessible(true);
        $this->assertEquals($this->getDatasetValue("t_child1",0,'F_TIMESTAMP'),$fTimestampReflection->getValue($tChild1Obj));

        $TParentReflection = new \ReflectionClass('PhpPlatform\Tests\Persist\Dao\TParent');
        $fIntReflection = $TParentReflection->getProperty("fInt");
        $fIntReflection->setAccessible(true);
        $this->assertEquals($this->getDatasetValue("t_parent",0,'F_INT'),$fIntReflection->getValue($tChild1Obj));

        $fDecimalReflection = $TParentReflection->getProperty("fDecimal");
        $fDecimalReflection->setAccessible(true);
        $this->assertEquals($this->getDatasetValue("t_parent",0,'F_DECIMAL'),$fDecimalReflection->getValue($tChild1Obj));

        $TSuperParentReflection = new \ReflectionClass('PhpPlatform\Tests\Persist\Dao\TSuperParent');
        $fVarcharReflection = $TSuperParentReflection->getProperty("fVarchar");
        $fVarcharReflection->setAccessible(true);
        $this->assertEquals($this->getDatasetValue("t_super_parent",0,'F_VARCHAR'),$fVarcharReflection->getValue($tChild1Obj));


        // test for constructor with argument - for Foreign Values
        $TChild2Reflection = new \ReflectionClass('PhpPlatform\Tests\Persist\Dao\TChild2');

        $tChild2Obj = new TChild2(1);

        $fForeignVarcharReflection = $TChild2Reflection->getProperty("fForeignVarchar");
        $fForeignVarcharReflection->setAccessible(true);
        $this->assertEquals($this->getDatasetValue("t_normal2",0,'F_VARCHAR'),$fForeignVarcharReflection->getValue($tChild2Obj));


        // test with access filters
        $isException = false;
        try{
            new TParent(1);
        }catch (NoAccessException $e){
            $isException = true;
        }
        $this->assertTrue($isException);
    }
    
    function testNewInstanceWithoutConstructor(){
    	// normal test
    	$newInstanceWithoutConstructor = new \ReflectionMethod('PhpPlatform\Persist\Model::__newInstanceWithoutConstructor');
    	$newInstanceWithoutConstructor->setAccessible(true);
    	$tNormal2Obj = $newInstanceWithoutConstructor->invokeArgs(null, array('PhpPlatform\Tests\Persist\Dao\TNormal2'));
    	
    	parent::assertEquals('PhpPlatform\Tests\Persist\Dao\TNormal2', get_class($tNormal2Obj));
    	
    	// error test
    	$isException = false;
    	try{
    		$tNormal2Obj = $newInstanceWithoutConstructor->invokeArgs(null, array('NonExistingClass'));
    	}catch (\Exception $e){
    		parent::assertEquals('Class NonExistingClass does not exist', $e->getMessage());
    		$isException = true;
    	}
    	$this->assertTrue($isException);
    	
    }
    

}