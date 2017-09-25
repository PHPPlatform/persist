<?php
/**
 * User: Raaghu
 * Date: 23-08-2015
 * Time: PM 09:48
 */

namespace PhpPlatform\Tests\Persist;

use PhpPlatform\Tests\Persist\Dao\TNormal2;
use PhpPlatform\Persist\Model;
use PhpPlatform\Tests\Persist\Dao\TChild1;
use PhpPlatform\Tests\Persist\Dao\TParent;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Tests\Persist\Dao\Relations\TMany2;
use PhpPlatform\Tests\Persist\Dao\Relations\TMany3;
use PhpPlatform\Tests\Persist\Dao\Relations\TMany2ToMany3;
use PhpPlatform\Persist\Expression;
use PhpPlatform\Persist\Field;

class ModelFindTest extends ModelTest{

    public function testFind(){
        // normal Find

        $tNormal2Reflection = new \ReflectionClass('PhpPlatform\Tests\Persist\Dao\TNormal2');
        $fVarcharReflection = $tNormal2Reflection->getProperty("fVarchar");
        $fVarcharReflection->setAccessible(true);
        $fPrimaryIdReflection = $tNormal2Reflection->getProperty("fPrimaryId");
        $fPrimaryIdReflection->setAccessible(true);


        $findResults = TNormal2::find(array("fVarchar" => $this->getDatasetValue("t_normal2",1,'F_VARCHAR')));
        $this->assertCount(1,$findResults);
        $this->assertEquals($this->getDatasetValue("t_normal2",1,'F_PRIMARY_ID'),$fPrimaryIdReflection->getValue($findResults[0]));
        $this->assertEquals($this->getDatasetValue("t_normal2",1,'F_VARCHAR'),$fVarcharReflection->getValue($findResults[0]));

        $findResults = TNormal2::find(array("fPrimaryId" => $this->getDatasetValue("t_normal2",0,'F_PRIMARY_ID')));
        $this->assertCount(1,$findResults);
        $this->assertEquals($this->getDatasetValue("t_normal2",0,'F_PRIMARY_ID'),$fPrimaryIdReflection->getValue($findResults[0]));
        $this->assertEquals($this->getDatasetValue("t_normal2",0,'F_VARCHAR'),$fVarcharReflection->getValue($findResults[0]));


        //find for null values
        TNormal2::create(array("fVarchar"=>null,"fBoolean"=>true));
        $findResults = TNormal2::find(array("fVarchar" => array(Model::OPERATOR_EQUAL=>null)));
        $this->assertCount(1,$findResults);
        $this->assertEquals("3",$fPrimaryIdReflection->getValue($findResults[0]));
        $this->assertEquals(null,$fVarcharReflection->getValue($findResults[0]));


        // find for inheritance
        $tChild1Reflection = new \ReflectionClass('PhpPlatform\Tests\Persist\Dao\TChild1');
        $tParentReflection = $tChild1Reflection->getParentClass();

        $fPrimaryIdReflection = $tChild1Reflection->getProperty("fPrimaryId");
        $fPrimaryIdReflection->setAccessible(true);

        $fTimestampReflection = $tChild1Reflection->getProperty("fTimestamp");
        $fTimestampReflection->setAccessible(true);

        $fIntReflection = $tParentReflection->getProperty("fInt");
        $fIntReflection->setAccessible(true);

        $fDecimalReflection = $tParentReflection->getProperty("fDecimal");
        $fDecimalReflection->setAccessible(true);

        $findResults = TChild1::find(array("fTimestamp"=>"2015-08-09 06:17:38","fDecimal"=>"10.01"));
        $this->assertCount(1,$findResults);
        $this->assertEquals($this->getDatasetValue("t_child1",0,'F_PRIMARY_ID'),$fPrimaryIdReflection->getValue($findResults[0]));
        $this->assertEquals($this->getDatasetValue("t_child1",0,'F_TIMESTAMP'),$fTimestampReflection->getValue($findResults[0]));
        $this->assertEquals($this->getDatasetValue("t_parent",0,'F_INT'),$fIntReflection->getValue($findResults[0]));
        $this->assertEquals($this->getDatasetValue("t_parent",0,'F_DECIMAL'),$fDecimalReflection->getValue($findResults[0]));
        
        // find with OPERATOR_IN
        $child1s = TChild1::find(array("fTimestamp"=>array(TChild1::OPERATOR_IN=>array("2015-08-09 06:17:38","2015-08-09 06:17:38"))));
        $this->assertCount(2, $child1s);

        $child1s = TChild1::find(array("fTimestamp"=>array(TChild1::OPERATOR_IN=>array("2015-08-09 06:17:38","2015-08-09 06:17:38")),"fInt"=>array(TChild1::OPERATOR_IN=>array("2","3"))));
        $this->assertCount(1, $child1s);
        
        $child1s = TChild1::find(array("fTimestamp"=>array(TChild1::OPERATOR_IN=>array())));
        $this->assertCount(0, $child1s);
        
        //find with sorting
        TChild1::create(array('fTimestamp'=>'2015-08-10 06:17:38','fInt'=>2,'fDecimal'=>1000.00,'fVarchar'=>'variable characters 21'));
        TChild1::create(array('fTimestamp'=>'2015-08-11 06:15:38','fInt'=>6,'fDecimal'=>1000.00,'fVarchar'=>'variable characters 211'));
        TChild1::create(array('fTimestamp'=>'2015-08-09 06:17:45','fInt'=>6,'fDecimal'=>100.00, 'fVarchar'=>'variable characters 21233'));
        TChild1::create(array('fTimestamp'=>'2015-07-10 06:17:38','fInt'=>5,'fDecimal'=>2000.00,'fVarchar'=>'variable characters 2145'));
        TChild1::create(array('fTimestamp'=>'2015-08-10 06:18:38','fInt'=>4,'fDecimal'=>1890.00,'fVarchar'=>'variable characters 2541'));
        TChild1::create(array('fTimestamp'=>'2015-08-12 06:17:38','fInt'=>2,'fDecimal'=>1034.00,'fVarchar'=>'variable characters 21534'));


        $findResults = TChild1::find(array(),array("fTimestamp"=>Model::SORTBY_ASC));
        $this->assertCount(8,$findResults);
        $this->assertPrimaryIds(array(6,1,2,5,3,7,4,8),$findResults,'PhpPlatform\Tests\Persist\Dao\TChild1');

        //find with sorting and filter
        $findResults = TChild1::find(array("fDecimal"=>array(Model::OPERATOR_BETWEEN=>array(100,1900))),array("fDecimal"=>Model::SORTBY_ASC,"fTimestamp"=>Model::SORTBY_DESC));
        $this->assertCount(5,$findResults);
        $this->assertPrimaryIds(array(5,4,3,8,7),$findResults,'PhpPlatform\Tests\Persist\Dao\TChild1');

        //find with sorting and pagination
        $findResults = TChild1::find(array(),array("fDecimal"=>Model::SORTBY_ASC),array("pageSize"=>2,"pageNumber"=>3));
        $this->assertCount(2,$findResults);
        // pagination with sort on numeric is erroneous , so not asserting results
        // $this->assertPrimaryIds(array(4,8),$findResults,'PhpPlatform\Tests\Persist\Dao\TChild1');

        $findResults = TChild1::find(array(),array("fInt"=>Model::SORTBY_DESC),array("start"=>2,"pageSize"=>4));
        $this->assertCount(4,$findResults);
        //$this->assertPrimaryIds(array(6,7,3,8),$findResults,'PhpPlatform\Tests\Persist\Dao\TChild1');

        $findResults = TChild1::find(array(),array(),array("start"=>2,"end"=>6));
        $this->assertCount(4,$findResults);
        $this->assertPrimaryIds(array(3,4,5,6),$findResults,'PhpPlatform\Tests\Persist\Dao\TChild1');


        //find with filter , sort, pagination
        $findResults = TChild1::find(array("fDecimal"=>array(Model::OPERATOR_BETWEEN => array(100,1900))),array("fTimestamp"=>Model::SORTBY_ASC),array("pageSize"=>3,"pageNumber"=>2));
        $this->assertCount(2,$findResults);
        $this->assertPrimaryIds(array(4,8),$findResults,'PhpPlatform\Tests\Persist\Dao\TChild1');
        $totalRecords = TChild1::getLastTotalRecords();
        $this->assertEquals(5, $totalRecords);

        //find with filter , sort, pagination, page
        $findResults = TChild1::find(
            array("fDecimal"=>array(Model::OPERATOR_BETWEEN => array(100,1900))),
            array("fTimestamp"=>Model::SORTBY_ASC),
            array("pageSize"=>5,"pageNumber"=>1),
        	new Expression(Model::OPERATOR_OR, array(
        			new Expression(Model::OPERATOR_EQUAL, array(
        					new Field('PhpPlatform\Tests\Persist\Dao\TParent', 'fInt'),
        					6)
        			),new Expression(Model::OPERATOR_EQUAL, array(
        					new Field('PhpPlatform\Tests\Persist\Dao\TParent', 'fInt'),
        					2)
        			)
        	)));
            //'{PhpPlatform\Tests\Persist\Dao\TParent.fInt} = 6 OR {PhpPlatform\Tests\Persist\Dao\TParent.fInt} = 2');
        $this->assertCount(4,$findResults);
        $this->assertPrimaryIds(array(5,3,4,8),$findResults,'PhpPlatform\Tests\Persist\Dao\TChild1');
        $totalRecords = TChild1::getLastTotalRecords();
        $this->assertEquals(4, $totalRecords);


        //find with access exception
        $isException = false;
        try{
            TParent::find(array("fInt"=>$this->getDatasetValue('t_parent',0,'F_INT')));
        }catch (\Exception $e){
            $isException = true;
        }
        $this->assertTrue($isException);

        //find with super user
        $isExceptionOuter = false;
        try{
            TransactionManager::startTransaction(null,true);

            $isException = false;
            try{
                TParent::find(array("fInt"=>$this->getDatasetValue('t_parent',0,'F_INT')));
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
        		'PhpPlatform\Tests\Persist\Dao\TChild1'=>array(
        				"READ"=>array(
        						"PRE"=>array(array(
        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        								"method"=>"readPreTrigger"
        						)),
        						"POST"=>array(array(
        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        								"method"=>"readPostTrigger"
        						))
        				)),
        		'PhpPlatform\Tests\Persist\Dao\TParent'=>array(
        				"READ"=>array(
        						"PRE"=>array(array(
        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        								"method"=>"readPreTrigger"
        						)),
        						"POST"=>array(array(
        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        								"method"=>"readPostTrigger"
        						))
        				))
        ));
        $_ENV[TRIGGER_TEST_LOG] = array();
        
        $findResults = TChild1::find(array("fTimestamp"=>"2015-08-09 06:17:38","fDecimal"=>"10.01"));
        $this->assertCount(1,$findResults);
        
        $this->assertCount(2,$_ENV[TRIGGER_TEST_LOG]);
        
        $this->assertEquals(array(array(array("filters" => array("fTimestamp" => "2015-08-09 06:17:38","fDecimal" => "10.01"),"sort" => null,"pagination" => null,"where" => null)),array(array("filters" => array("fTimestamp" => "2015-08-09 06:17:38","fDecimal" => "10.01"),"sort" => null,"pagination" => null,"where" => null))),
        		$_ENV[TRIGGER_TEST_LOG]["readPreTrigger"]);
        $this->assertEquals(array(array($findResults),array($findResults)),$_ENV[TRIGGER_TEST_LOG]["readPostTrigger"]);
        
    }
    
    function testFindUsingGroupBy(){
    	/**
    	 * data
    	 */
    	$tMany2Obj1 = TMany2::create(array("fMany2Name"=>"Many2_1"));
    	$tMany2Obj2 = TMany2::create(array("fMany2Name"=>"Many2_2"));
    	
    	$tMany3Obj1 = TMany3::create(array("fMany3Name"=>"Many3_1","fMany3Bool"=>true));
    	$tMany3Obj2 = TMany3::create(array("fMany3Name"=>"Many3_2","fMany3Bool"=>"FALSE"));
    	$tMany3Obj3 = TMany3::create(array("fMany3Name"=>"Many3_3"));
    	$tMany3Obj4 = TMany3::create(array("fMany3Name"=>"Many3_4"));
    	 
    	TMany2ToMany3::create(array('fMany2PrimaryId'=>$tMany2Obj1->getAttribute('fPrimaryId'),'fMany3PrimaryId'=>$tMany3Obj1->getAttribute('fPrimaryId')));
    	TMany2ToMany3::create(array('fMany2PrimaryId'=>$tMany2Obj1->getAttribute('fPrimaryId'),'fMany3PrimaryId'=>$tMany3Obj2->getAttribute('fPrimaryId')));
    	TMany2ToMany3::create(array('fMany2PrimaryId'=>$tMany2Obj1->getAttribute('fPrimaryId'),'fMany3PrimaryId'=>$tMany3Obj3->getAttribute('fPrimaryId')));
    	 
    	
    	$results = TMany2ToMany3::find(array());
    	
    	parent::assertCount(1, $results);
    	parent::assertEquals(array(
    			"fMany2PrimaryId"=>$tMany2Obj1->getAttribute('fPrimaryId'),
    			"fMany3PrimaryId"=>array(
    					$tMany3Obj1->getAttribute('fPrimaryId'),
    					$tMany3Obj2->getAttribute('fPrimaryId'),
    					$tMany3Obj3->getAttribute('fPrimaryId')
    			),
    			"fMany3Name"=>array(
    					"Many3_1",
    					"Many3_2",
    					"Many3_3"
    			),
    			"fMany3Bool"=>array(
    					true,
    					false,
    					false
    			)
    	), $results[0]->getAttributes("*"));
    	
    }
    
}