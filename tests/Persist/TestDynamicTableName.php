<?php
/**
 * User: Raaghu
* Date: 23-08-2015
* Time: PM 09:48
*/

namespace PhpPlatform\Tests\Persist;

use PhpPlatform\Tests\Persist\Dao\TNormal2WithDynamicTableName;

class TestDynamicTableName extends ModelTest{
	
	function testConstruct(){
		$tNormal2 = new TNormal2WithDynamicTableName(1);
		$this->assertEquals($this->getDatasetValue("t_normal2",0,'F_VARCHAR'),$tNormal2->getAttribute('fVarchar'));
	}
	
	function testCreate(){
		$tNormal2 = TNormal2WithDynamicTableName::create(array("fVarchar"=>"variable characters 111","fBoolean"=>true));
		$this->assertEquals("variable characters 111", $tNormal2->getAttribute('fVarchar'));
	}
	
	function testFind(){
		$tNormal2s = TNormal2WithDynamicTableName::find(array('fPrimaryId'=>array(TNormal2WithDynamicTableName::OPERATOR_IN=>array(1))));
		$this->assertCount(1, $tNormal2s);
		$this->assertEquals($this->getDatasetValue("t_normal2",0,'F_VARCHAR'),$tNormal2s[0]->getAttribute('fVarchar'));
	}
	
	function testUpdate(){
		$tNormal2 = new TNormal2WithDynamicTableName(1);
		$tNormal2->setAttribute('fVarchar', "TNormal2WithDynamicTableName");
		$this->assertEquals("TNormal2WithDynamicTableName",$tNormal2->getAttribute('fVarchar'));
	}
	
	function testDelete(){
		$tNormal2 = TNormal2WithDynamicTableName::create(array("fVarchar"=>"variable characters 111","fBoolean"=>true));
		$tNormal2->delete();
		
		$tNormal2s = TNormal2WithDynamicTableName::find(array("fVarchar"=>"variable characters 111"));
		$this->assertCount(0, $tNormal2s);
	}
	
	
	
}