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

class ModelCreateTest extends ModelTest{

    public function testCreate(){
        // normal create
        $tNormal2 = TNormal2::create(array("fVarchar"=>"variable characters 111"));

        $this->assertSelect(array(
            't_normal2' => array(
                array(
                    'F_PRIMARY_ID' => 3,
                    'F_VARCHAR' => 'variable characters 111'
                )
            )
        ),'SELECT * FROM t_normal2 WHERE f_primary_id = 3');


        $TNormal2Reflection = new \ReflectionClass('PhpPlatform\Tests\Persist\Dao\TNormal2');
        $isObjectInitialisedReflection = $TNormal2Reflection->getProperty("isObjectInitialised");
        $isObjectInitialisedReflection->setAccessible(true);
        $this->assertTrue($isObjectInitialisedReflection->getValue($tNormal2));


        // test for create with null values
        $tNormal2 = TNormal2::create(array("fVarchar"=>null));

        $this->assertSelect(array(
            't_normal2' => array(
                array(
                    'F_PRIMARY_ID' => 4,
                    'F_VARCHAR' => null
                )
            )
        ),'SELECT * FROM t_normal2 WHERE f_primary_id = 4');

        $this->assertTrue($isObjectInitialisedReflection->getValue($tNormal2));


        // create with inheritance
        $tChild1 = TChild1::create(array(
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

        $TChild1Reflection = new \ReflectionClass('PhpPlatform\Tests\Persist\Dao\TChild1');
        $isObjectInitialisedReflection = $TChild1Reflection->getProperty("isObjectInitialised");
        $isObjectInitialisedReflection->setAccessible(true);

        $this->assertTrue($isObjectInitialisedReflection->getValue($tChild1));

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
        
        
        //test Trigger
        parent::setTriggers(array(
        				  'PhpPlatform\Tests\Persist\Dao\TChild1'=>array(
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
        $tChild1 = TChild1::create(array(
        		'fTimestamp'=>'2015-08-10 06:17:38',
        		'fInt'=>10,
        		'fDecimal'=>10.20,
        		'fVarchar'=>'variable characters for pretrigger testing'
        ));
        
        $this->assertCount(2,$_ENV[TRIGGER_TEST_LOG]);
        $this->assertEquals(array(array($tChild1),array($tChild1)),$_ENV[TRIGGER_TEST_LOG]["createPreTrigger"]);
        $this->assertEquals(array(array($tChild1),array($tChild1)),$_ENV[TRIGGER_TEST_LOG]["createPostTrigger"]);
        
    }
}