<?php
/**
 * User: Raaghu
 * Date: 23-08-2015
 * Time: PM 09:40
 */

namespace PhpPlatform\Tests\Persist;

use PhpPlatform\Tests\Persist\Dao\TNormal2;
use PhpPlatform\Tests\Persist\Dao\TChild1;
use PhpPlatform\Tests\Persist\Dao\TParent;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Tests\Persist\Dao\TChild2;
use PhpPlatform\Errors\Exceptions\Persistence\BadQueryException;
use PhpPlatform\Persist\Exception\InvalidInputException;

class ModelCreateTest extends ModelTest{

    public function testCreate(){
        // normal create
        $tNormal2Obj = TNormal2::create(array("fVarchar"=>"variable characters 111","fBoolean"=>true));

        $this->assertSelect(array(
            't_normal2' => array(
                array(
                    'F_PRIMARY_ID' => 3,
                    'F_VARCHAR' => 'variable characters 111',
                	'F_BOOLEAN' => 1
                )
            )
        ),'SELECT * FROM t_normal2 WHERE f_primary_id = 3');

        // test for create with null values
        TNormal2::create(array("fVarchar"=>null,"fBoolean"=>false));

        $this->assertSelect(array(
            't_normal2' => array(
                array(
                    'F_PRIMARY_ID' => 4,
                    'F_VARCHAR' => null,
                	'F_BOOLEAN' => 0
                )
            )
        ),'SELECT * FROM t_normal2 WHERE f_primary_id = 4');


        // create with inheritance
        TChild1::create(array(
        	'fTimestamp'=>'2015-08-10 06:17:38',
        	'fInt'=>10,
        	'fDecimal'=>10.20,
        	'fVarchar'=>'variable characters 222'
        ));

        $this->assertSelect(array(
            't_child1' => array(
                array(
                    'F_PRIMARY_ID' => 3,
                    'F_TIMESTAMP' => '2015-08-10 06:17:38',
                    'F_PARENT_ID' => 5
                )
            )
        ),'SELECT * FROM t_child1 WHERE f_primary_id = 3');

        $this->assertSelect(array(
            't_parent' => array(
                array(
                    'F_PRIMARY_ID' => 5,
                    'F_INT' => 10,
                    'F_DECIMAL' => 10.20,
                    'F_PARENT_ID' => 5
                )
            )
        ),'SELECT * FROM t_parent WHERE f_primary_id = 5');

        $this->assertSelect(array(
            't_super_parent' => array(
                array(
                    'F_PRIMARY_ID' => 5,
                    'F_VARCHAR' => 'variable characters 222'
                )
            )
        ),'SELECT * FROM t_super_parent WHERE f_primary_id = 5');
        
        //create with wrong value for boolean
        $isException = false;
        try{
        	TNormal2::create(array("fVarchar"=>null,"fBoolean"=>"it is true"));
        }catch (InvalidInputException $e){
        	$isException = true;
        	parent::assertEquals("Expected boolean value for fBoolean", $e->getMessage());
        }
        $this->assertTrue($isException);
        
        //create with access exception
        $isException = false;
        try{
            TParent::create(array(
            		'fInt'=>1,
            		'fDecimal'=>12.2,
            		'fVarchar'=>'Variable chars'
            ));
        }catch (\Exception $e){
            $isException = true;
        }
        $this->assertTrue($isException);


        //create with super user
        $isExceptionOuter = false;
        try{
            TransactionManager::startTransaction(null,true);

            $isException = false;
            try{
                TParent::create(array(
            		'fInt'=>2,
            		'fDecimal'=>22.3,
            		'fVarchar'=>'Variable chars'
                ));
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
        
        // test for error during creation 
        $isException = false;
        try{
        	$tChild2 = TChild2::create(array(
        			'fDate'=>'2015-08-10',
        			'fInt'=>10,
        			'fDecimal'=>10.20,
        			'fVarchar'=>'variable characters for pretrigger testing'
        	));
        }catch (BadQueryException $e){
        	$isException = true;
        	parent::assertEquals('Error in creating PhpPlatform\Tests\Persist\Dao\TChild2 "Column \'F_FOREIGN\' cannot be null"', $e->getMessage());
        }
        parent::assertTrue($isException);
        
        
        
        
        //test Trigger
        parent::setTriggers(array(
        				  'PhpPlatform\Tests\Persist\Dao\TChild2'=>array(
        						"CREATE"=>array(
        								"PRE"=>array(array(
        										"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        										"method"=>"createPreTrigger"
        								)),
        								"POST"=>array(array(
        										"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
        										"method"=>"createPostTrigger"
        								))
        						)),
			        		'PhpPlatform\Tests\Persist\Dao\TParent'=>array(
			        				"CREATE"=>array(
			        						"PRE"=>array(array(
			        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
			        								"method"=>"createPreTrigger"
			        						)),
			        						"POST"=>array(array(
			        								"class"=>'PhpPlatform\Tests\Persist\SampleTrigger',
			        								"method"=>"createPostTrigger"
			        						))
			        				))
        ));
        $_ENV[TRIGGER_TEST_LOG] = array();
        $tChild2 = TChild2::create(array(
        		'fDate'=>'2015-08-10',
        		'fInt'=>10,
        		'fDecimal'=>10.20,
        		'fVarchar'=>'variable characters for pretrigger testing',
        		'fForeign'=>$tNormal2Obj->getAttribute('fPrimaryId')
        ));
        
        $this->assertCount(2,$_ENV[TRIGGER_TEST_LOG]);
        $this->assertEquals(array(array($tChild2),array($tChild2)),$_ENV[TRIGGER_TEST_LOG]["createPreTrigger"]);
        $this->assertEquals(array(array($tChild2),array($tChild2)),$_ENV[TRIGGER_TEST_LOG]["createPostTrigger"]);
        
    }
}