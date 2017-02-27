<?php

/**
 * User: Raaghu
 * Date: 02-08-2015
 * Time: PM 10:19
 */

namespace PhpPlatform\Tests\Persist;

use PhpPlatform\Tests\Persist\Dao\TNormal2;
use PhpPlatform\Tests\Persist\Dao\TChild2;
use PhpPlatform\Persist\TransactionManager;

class ModelGetPropertiesTest extends ModelTest{
    
    public function testGetProperties(){
        //Normal get properties

        $tNormal2 = new TNormal2(1);
        $tNormal2Varchar = $tNormal2->getAttribute("fVarchar");
        $this->assertEquals($this->getDatasetValue("t_normal2",0,'F_VARCHAR'),$tNormal2Varchar);

        $tNormal2 = new TNormal2(2);
        $tNormal2Properties = $tNormal2->getAttributes("*");
        $this->assertEquals(
        		array(
        				"fPrimaryId"=>$this->getDatasetValue("t_normal2",1,'F_PRIMARY_ID'),
                        "fVarchar"=>$this->getDatasetValue("t_normal2",1,'F_VARCHAR'),
        				"fBoolean"=>false
        		),$tNormal2Properties);

        //getProperties with inheritance
        $tChild2 = new TChild2(1);
        $tChild2Varchar = $tChild2->getAttribute("fVarchar");
        $this->assertEquals($this->getDatasetValue("t_super_parent",2,'F_VARCHAR'),$tChild2Varchar);

        $tChild2 = new TChild2(2);
        $tChild2Properties = $tChild2->getAttributes("*");

        $dateInOutputFormat = null;
        $pdo = self::$_pdo;
        $date = $this->getDatasetValue('t_child2',1,'F_DATE');
        TransactionManager::executeInTransaction(function() use(&$dateInOutputFormat,$pdo,$date){
        	$dateInOutputFormat = $pdo->query("SELECT date_format('".$date."','".TransactionManager::getConnection()->outputDateFormat()."') as dateInOutputFormat;");
        });
        $dateInOutputFormat = $dateInOutputFormat->fetchColumn(0);

        $this->assertEquals(array(
            "fPrimaryId"=>$this->getDatasetValue('t_child2',1,'F_PRIMARY_ID'),
            "fDate"     =>$dateInOutputFormat,
            "fParentId" =>$this->getDatasetValue('t_child2',1,'F_PARENT_ID'),
            "fForeign"  =>$this->getDatasetValue('t_child2',1,'F_FOREIGN'),
            "fForeignVarchar"=>$this->getDatasetValue('t_normal2',1,'F_VARCHAR'),
            "fInt"      =>$this->getDatasetValue('t_parent',3,'F_INT'),
            "fDecimal"  =>$this->getDatasetValue('t_parent',3,'F_DECIMAL'),
            "fVarchar"  =>$this->getDatasetValue('t_super_parent',3,'F_VARCHAR')
        ),$tChild2Properties);

    }

}