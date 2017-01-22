<?php
/**
 * User: Raaghu
 * Date: 23-08-2015
 * Time: PM 09:41
 */

namespace PhpPlatform\Tests\Persist;

use PhpPlatform\Tests\Persist\Dao\TNormal1;
use PhpPlatform\Tests\Persist\Dao\TChild1;
use PhpPlatform\Tests\Persist\Dao\TParent;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Tests\Persist\Dao\TChild2;

class ModelDeleteTest extends ModelTest{

    public function testDelete(){
        // normal Delete
        $tNormal1 = new TNormal1(1);
        $tNormal1->delete();

        $this->assertSelect(array(
            "t_normal1"=>array($this->getDataset()->getTable("t_normal1")->getRow(1))
        ),"SELECT * FROM t_normal1");

        // delete with inheritance
        $tChild1 = new TChild1(1);
        $tChild1->delete();

        $this->assertSelect(array(
            "t_child1"=>array($this->getDataset()->getTable("t_child1")->getRow(1))
        ),"SELECT * FROM t_child1");

        $this->assertSelect(array(
            "t_parent"=>array($this->getDataset()->getTable("t_parent")->getRow(1),
                $this->getDataset()->getTable("t_parent")->getRow(2),
                $this->getDataset()->getTable("t_parent")->getRow(3))
        ),"SELECT * FROM t_parent");

        $this->assertSelect(array(
            "t_super_parent"=>array($this->getDataset()->getTable("t_super_parent")->getRow(1),
                $this->getDataset()->getTable("t_super_parent")->getRow(2),
                $this->getDataset()->getTable("t_super_parent")->getRow(3))
        ),"SELECT * FROM t_super_parent");


        $isException = false;
        $parentObj = new TParent();
        $parentObjReflection = new \ReflectionClass($parentObj);

        $fPrimaryId = $parentObjReflection->getProperty('fPrimaryId');
        $fPrimaryId->setAccessible(true);
        $fPrimaryId->setValue($parentObj,$this->getDatasetValue('t_parent',0,'F_PRIMARY_ID'));

        $fInt = $parentObjReflection->getProperty('fInt');
        $fInt->setAccessible(true);
        $fInt->setValue($parentObj,$this->getDatasetValue('t_parent',0,'F_INT'));

        $fDecimal = $parentObjReflection->getProperty('fDecimal');
        $fDecimal->setAccessible(true);
        $fDecimal->setValue($parentObj,$this->getDatasetValue('t_parent',0,'F_DECIMAL'));

        $fParentId = $parentObjReflection->getProperty('fParentId');
        $fParentId->setAccessible(true);
        $fParentId->setValue($parentObj,$this->getDatasetValue('t_parent',0,'F_PARENT_ID'));

        $superParentObjReflection = $parentObjReflection->getParentClass();
        $fPrimaryId = $superParentObjReflection->getProperty('fPrimaryId');
        $fPrimaryId->setAccessible(true);
        $fPrimaryId->setValue($parentObj,$this->getDatasetValue('t_super_parent',0,'F_PRIMARY_ID'));

        $fVarchar = $superParentObjReflection->getProperty('fVarchar');
        $fVarchar->setAccessible(true);
        $fVarchar->setValue($parentObj,$this->getDatasetValue('t_super_parent',0,'F_VARCHAR'));

        $ModelObjReflection = new \ReflectionClass('PhpPlatform\Persist\Model');
        $isObjectInitialised = $ModelObjReflection->getProperty('isObjectInitialised');
        $isObjectInitialised->setAccessible(true);
        $isObjectInitialised->setValue($parentObj,true);


        // delete with access expression
        try{
            $parentObj->delete();
        }catch (\Exception $e){
            $isException = true;
        }
        $this->assertTrue($isException);


        // delete with super user
        $isExceptionOuter = false;
        try{
            TransactionManager::startTransaction(null,true);
            $isException = false;
            try{
                $parentObj->delete();
            }catch (\Exception $e){
                $isException = true;
            }
            $this->assertTrue(!$isException);
            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            $isExceptionOuter = true;
        }
        $this->assertTrue(!$isExceptionOuter);

        //test Trigger
        parent::setTriggers(array(
        		'PhpPlatform\Tests\Persist\Dao\TChild2'=>array(
        				"DELETE"=>array(
        						"PRE"=>array(array(
        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        								"method"=>"deletePreTrigger"
        						)),
        						"POST"=>array(array(
        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        								"method"=>"deletePostTrigger"
        						))
        				)),
        		'PhpPlatform\Tests\Persist\Dao\TParent'=>array(
        				"DELETE"=>array(
        						"PRE"=>array(array(
        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        								"method"=>"deletePreTrigger"
        						)),
        						"POST"=>array(array(
        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        								"method"=>"deletePostTrigger"
        						))
        				))
        ));
        $_ENV[TRIGGER_TEST_LOG] = array();
        $tChild2 = new TChild2(1);
        $tChild2->delete();
        
        $this->assertEquals(array(array($tChild2),array($tChild2)),$_ENV[TRIGGER_TEST_LOG]["deletePreTrigger"]);
        $this->assertEquals(array(array($tChild2),array($tChild2)),$_ENV[TRIGGER_TEST_LOG]["deletePostTrigger"]);
        
    }
}