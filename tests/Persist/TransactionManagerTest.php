<?php
/**
 * Created by IntelliJ IDEA.
 * User: Raaghu
 * Date: 04-10-2015
 * Time: AM 10:31
 */

namespace PhpPlatform\Tests\Persist;

use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Tests\Persist\Dao\TNormal2;

class TransactionManagerTest extends ModelTest{

    public function testNormalTransaction(){

        // Normal successful transaction
        try{
            TransactionManager::startTransaction();

            TNormal2::create(array('fVarchar'=>"variable characters 111","fBoolean"=>true));

            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
        }

        $this->assertSelect(array(
            't_normal2' => array(
                array(
                    'F_PRIMARY_ID' => 3,
                    'F_VARCHAR' => 'variable characters 111',
                	'F_BOOLEAN' => 1	
                )
            )
        ),'SELECT * FROM t_normal2 WHERE f_primary_id = 3');


        // Normal failed transaction
        $throwExp = true;
        try{
            TransactionManager::startTransaction();

            TNormal2::create(array('fVarchar'=>"variable characters 222","fBoolean"=>true));

            if($throwExp){
                throw new \Exception("");
            }

            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
        }

        $this->assertSelect(array(
            't_normal2' => array(
            )
        ),'SELECT * FROM t_normal2 WHERE f_primary_id = 4');



    }

    public function testNestedTransactions(){

        try{
            TransactionManager::startTransaction();

            TNormal2::create(array('fVarchar'=>"variable characters 111","fBoolean"=>true));

            // inner transaction
            try{
                TransactionManager::startTransaction();

                TNormal2::create(array('fVarchar'=>"variable characters 222","fBoolean"=>true));

                // inner-inner transaction
                try{
                    TransactionManager::startTransaction();

                    try{
                        TransactionManager::startTransaction();
                        TNormal2::create(array('fVarchar'=>"variable characters 333","fBoolean"=>true));
                        TransactionManager::commitTransaction();
                    }catch (\Exception $e){
                        TransactionManager::abortTransaction();
                    }


                    // inner-inner-inner transaction
                    $throwExp = true;
                    try{
                        TransactionManager::startTransaction();

                        TNormal2::create(array('fVarchar'=>"variable characters 444","fBoolean"=>true));

                        if($throwExp){
                            throw new \Exception("");
                        }

                        TransactionManager::commitTransaction();
                    }catch (\Exception $e){
                        TransactionManager::abortTransaction();
                        throw $e;
                    }

                    TransactionManager::commitTransaction();
                }catch (\Exception $e){
                    TransactionManager::abortTransaction();
                }

                // inner-inner transaction
                try{
                    TransactionManager::startTransaction();

                    TNormal2::create(array('fVarchar'=>"variable characters 555","fBoolean"=>false));

                    TransactionManager::commitTransaction();
                }catch (\Exception $e){
                    TransactionManager::abortTransaction();
                }

                TransactionManager::commitTransaction();
            }catch (\Exception $e){
                TransactionManager::abortTransaction();
            }

            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
        }

        $this->assertSelect(array(
            't_normal2' => array(
                array(
                    'F_PRIMARY_ID' => 1,
                    'F_VARCHAR' => $this->getDatasetValue("t_normal2",0,"F_VARCHAR"),
                	'F_BOOLEAN' => $this->getDatasetValue("t_normal2",0,"F_BOOLEAN")	
                		
                ),
                array(
                    'F_PRIMARY_ID' => 2,
                    'F_VARCHAR' => $this->getDatasetValue("t_normal2",1,"F_VARCHAR"),
                	'F_BOOLEAN' => $this->getDatasetValue("t_normal2",1,"F_BOOLEAN")
                ),
                array(
                    'F_PRIMARY_ID' => 3,
                    'F_VARCHAR' => 'variable characters 111',
                	'F_BOOLEAN' => 1
                ),
                array(
                    'F_PRIMARY_ID' => 4,
                    'F_VARCHAR' => 'variable characters 222',
                	'F_BOOLEAN' => 1
                ),
                array(
                    'F_PRIMARY_ID' => 7,
                    'F_VARCHAR' => 'variable characters 555',
                	'F_BOOLEAN' => 0
                )
            )
        ),'SELECT * FROM t_normal2 ');

    }

}