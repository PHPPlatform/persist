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
use PhpPlatform\Errors\Exceptions\Persistence\BadQueryException;

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
        
        // construct a parent object
        $parentObj = null;
        $parentFPrimaryId = $this->getDatasetValue('t_parent',1,'F_PRIMARY_ID');
        TransactionManager::executeInTransaction(function () use(&$parentObj,$parentFPrimaryId){
        	$parentObj = new TParent($parentFPrimaryId);
        },array(),true);


        // delete with access expression set to false
        $isException = true;
        try{
            $parentObj->delete();
        }catch (\Exception $e){
            $isException = true;
        }
        $this->assertTrue($isException);


        // delete with super user , with foreign key constraint preventing it
        $isException = false;
        try{
            TransactionManager::startTransaction(null,true);
            $parentObj->delete();
            TransactionManager::commitTransaction();
        }catch (BadQueryException $e){
        	TransactionManager::abortTransaction();
            $isException = true;
        }
        $this->assertTrue($isException);
        
        // remove foreign key constraint
        try{
        	TransactionManager::startTransaction();
        	$dbs = TransactionManager::getConnection();
        	$dbs->query("DELETE FROM t_normal1 where F_PRIMARY_ID = '".$this->getDatasetValue('t_normal1',1,'F_PRIMARY_ID')."'");
        	$dbs->query("DELETE FROM t_child1 where F_PRIMARY_ID = '".$this->getDatasetValue('t_child1',1,'F_PRIMARY_ID')."'");
        	TransactionManager::commitTransaction();
        }catch (BadQueryException $e){
        	TransactionManager::abortTransaction();
        	throw $e;
        }
        
        // delete with super user , with out any foreign key constraint preventing it
        $isException = false;
        try{
        	TransactionManager::startTransaction(null,true);
        	$parentObj->delete();
        	TransactionManager::commitTransaction();
        }catch (BadQueryException $e){
        	TransactionManager::abortTransaction();
        	$isException = true;
        }
        $this->assertTrue(!$isException);

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