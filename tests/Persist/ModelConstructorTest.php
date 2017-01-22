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

class ModelConstructorTest extends ModelTest{
    

    public function testConstruct(){

        // test for no exceptions while constructing
        $isException = false;
        try{
            new TNormal1();
        }catch (\Exception $e){
            echo $e;
            $isException = true;
        }
        $this->assertTrue(!$isException);

        $TNormal2Reflection = new \ReflectionClass('PhpPlatform\Tests\Persist\Dao\TNormal2');
        $isObjectInitialisedReflection = $TNormal2Reflection->getProperty("isObjectInitialised");
        $isObjectInitialisedReflection->setAccessible(true);

        // test for no argument constructor
        $tNormal2Obj = new TNormal2();
        $this->assertTrue(!$isObjectInitialisedReflection->getValue($tNormal2Obj));

        // test for constructor with argument
        $tNormal2Obj = new TNormal2(1);
        $this->assertTrue($isObjectInitialisedReflection->getValue($tNormal2Obj));

        $fPrimaryIdReflection = $TNormal2Reflection->getProperty("fPrimaryId");
        $fPrimaryIdReflection->setAccessible(true);
        $this->assertEquals("1",$fPrimaryIdReflection->getValue($tNormal2Obj));

        $fVarcharReflection = $TNormal2Reflection->getProperty("fVarchar");
        $fVarcharReflection->setAccessible(true);

        $this->assertEquals($this->getDatasetValue("t_normal2",0,'F_VARCHAR'),$fVarcharReflection->getValue($tNormal2Obj));

        // test for constructor with argument - for inherited Classes
        $TChild1Reflection = new \ReflectionClass('PhpPlatform\Tests\Persist\Dao\TChild1');
        $isObjectInitialisedReflection = $TChild1Reflection->getProperty("isObjectInitialised");
        $isObjectInitialisedReflection->setAccessible(true);

        $tChild1Obj = new TChild1();
        $this->assertTrue(!$isObjectInitialisedReflection->getValue($tChild1Obj));

        $tChild1Obj = new TChild1(1);
        $this->assertTrue($isObjectInitialisedReflection->getValue($tChild1Obj));

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
        $isObjectInitialisedReflection = $TChild2Reflection->getProperty("isObjectInitialised");
        $isObjectInitialisedReflection->setAccessible(true);

        $tChild2Obj = new TChild2(1);
        $this->assertTrue($isObjectInitialisedReflection->getValue($tChild2Obj));

        $fForeignVarcharReflection = $TChild2Reflection->getProperty("fForeignVarchar");
        $fForeignVarcharReflection->setAccessible(true);
        $this->assertEquals($this->getDatasetValue("t_normal2",0,'F_VARCHAR'),$fForeignVarcharReflection->getValue($tChild2Obj));


        $isException = false;
        try{
            new TParent(1);
        }catch (\Exception $e){
            $this->assertInstanceOf('PhpPlatform\Errors\Exceptions\Persistence\NoAccessException',$e);
            $isException = true;
        }
        $this->assertTrue($isException);

    }

}