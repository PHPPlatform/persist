<?php

/**
 * User: Raaghu
 * Date: 02-08-2015
 * Time: PM 10:19
 */

namespace PhpPlatform\Tests\Persist;


use PhpPlatform\Tests\Persist\Dao\TNormal2;
use PhpPlatform\Tests\Persist\Dao\TChild2;
use PhpPlatform\Tests\Persist\Dao\TParent;
use PhpPlatform\Persist\TransactionManager;

class ModelSetPropertiesTest extends ModelTest{

    public function testSetProperties(){
        //Normal set properties

        $tNormal2 = new TNormal2(1);
        $tNormal2->setAttribute("fVarchar",$this->getDatasetValue('t_normal2',1,'F_VARCHAR')."_");
        $tNormal2 = new TNormal2(1);
        $tNormal2Varchar = $tNormal2->getAttribute("fVarchar");
        $this->assertEquals($this->getDatasetValue("t_normal2",1,'F_VARCHAR')."_",$tNormal2Varchar);

        //setProperties with inheritance
        $tChild2 = new TChild2(1);
        $tChild2->setAttribute("fVarchar",$this->getDatasetValue("t_super_parent",3,'F_VARCHAR'));
        $tChild2 = new TChild2(1);
        $tChild2Varchar = $tChild2->getAttribute("fVarchar");
        $this->assertEquals($this->getDatasetValue("t_super_parent",3,'F_VARCHAR'),$tChild2Varchar);

        $tChild2 = new TChild2(2);
        
        $dateInOutputFormat = null;
        $pdo = self::$_pdo;
        $date = $this->getDatasetValue('t_child2',0,'F_DATE');
        TransactionManager::executeInTransaction(function() use(&$dateInOutputFormat,$pdo,$date){
        	$dateInOutputFormat = $pdo->query("SELECT date_format('".$date."','".TransactionManager::getConnection()->outputDateFormat()."') as dateInOutputFormat;");
        });
        
        $dateInOutputFormat = $dateInOutputFormat->fetchColumn(0);
        $tChild2->setAttributes(array(
            "fDate"     =>$dateInOutputFormat,
            "fForeign"  =>$this->getDatasetValue('t_child2',0,'F_FOREIGN'),
            "fInt"      =>$this->getDatasetValue('t_parent',2,'F_INT'),
            "fDecimal"  =>$this->getDatasetValue('t_parent',2,'F_DECIMAL'),
            "fVarchar"  =>$this->getDatasetValue('t_super_parent',2,'F_VARCHAR')
        ));


        $tChild2 = new TChild2(2);
        $tChild2Properties = $tChild2->getAttributes("*");

        $this->assertEquals(array(
            "fPrimaryId"=>$this->getDatasetValue('t_child2',1,'F_PRIMARY_ID'),
            "fDate"     =>$dateInOutputFormat,
            "fParentId" =>$this->getDatasetValue('t_child2',1,'F_PARENT_ID'),
            "fForeign"  =>$this->getDatasetValue('t_child2',0,'F_FOREIGN'),
            "fForeignVarchar"=>$this->getDatasetValue('t_normal2',1,'F_VARCHAR')."_", // this should be ('t_normal2',0,'F_VARCHAR'), but 1st row of TNormal2 is modified in first line of this testMethod
            "fInt"      =>$this->getDatasetValue('t_parent',2,'F_INT'),
            "fDecimal"  =>$this->getDatasetValue('t_parent',2,'F_DECIMAL'),
            "fVarchar"  =>$this->getDatasetValue('t_super_parent',2,'F_VARCHAR')
        ),$tChild2Properties);


        // construct a parent object
        $parentObj = null;
        $parentFPrimaryId = $this->getDatasetValue('t_parent',1,'F_PRIMARY_ID');
        TransactionManager::executeInTransaction(function () use(&$parentObj,$parentFPrimaryId){
        	$parentObj = new TParent($parentFPrimaryId);
        },array(),true);

        // set attributes with access expression
        $isException = false;
        try{
            $parentObj->setAttribute("fInt",$this->getDatasetValue('t_parent',2,'F_INT'));
        }catch (\Exception $e){
            $isException = true;
        }
        $this->assertTrue($isException);

        // set attribute with super user
        $isException = false;
        try{
            TransactionManager::startTransaction(null,true);
            $parentObj->setAttribute("fInt",$this->getDatasetValue('t_parent',2,'F_INT'));
            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            $isException = true;
        }
        $this->assertTrue(!$isException);
        
        
        
        //test Trigger
        parent::setTriggers(array(
        		'PhpPlatform\Tests\Persist\Dao\TChild2'=>array(
        				"UPDATE"=>array(
        						"PRE"=>array(array(
        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        								"method"=>"updatePreTrigger"
        						)),
        						"POST"=>array(array(
        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        								"method"=>"updatePostTrigger"
        						))
        				)),
        		'PhpPlatform\Tests\Persist\Dao\TParent'=>array(
        				"UPDATE"=>array(
        						"PRE"=>array(array(
        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        								"method"=>"updatePreTrigger"
        						)),
        						"POST"=>array(array(
        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        								"method"=>"updatePostTrigger"
        						))
        				))
        ));
        $_ENV[TRIGGER_TEST_LOG] = array();
        $tChild2 = new TChild2(1);
        $tChild2->setAttribute("fInt","1000");
        
        $this->assertEquals(array(array($tChild2,array("fInt" => 1000))),$_ENV[TRIGGER_TEST_LOG]["updatePreTrigger"]);
        $this->assertEquals(array(array($tChild2,array("fInt" => 1000))),$_ENV[TRIGGER_TEST_LOG]["updatePostTrigger"]);
    }
}