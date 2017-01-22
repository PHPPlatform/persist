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

            TNormal2::create(array('fVarchar'=>"variable characters 111"));

            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
        }

        $this->assertSelect(array(
            't_normal2' => array(
                array(
                    'F_PRIMARY_ID' => 3,
                    'F_VARCHAR' => 'variable characters 111'
                )
            )
        ),'SELECT * FROM t_normal2 WHERE f_primary_id = 3');


        // Normal failed transaction
        $throwExp = true;
        try{
            TransactionManager::startTransaction();

            TNormal2::create(array('fVarchar'=>"variable characters 222"));

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

            TNormal2::create(array('fVarchar'=>"variable characters 111"));

            // inner transaction
            try{
                TransactionManager::startTransaction();

                TNormal2::create(array('fVarchar'=>"variable characters 222"));

                // inner-inner transaction
                try{
                    TransactionManager::startTransaction();

                    try{
                        TransactionManager::startTransaction();
                        TNormal2::create(array('fVarchar'=>"variable characters 333"));
                        TransactionManager::commitTransaction();
                    }catch (\Exception $e){
                        TransactionManager::abortTransaction();
                    }


                    // inner-inner-inner transaction
                    $throwExp = true;
                    try{
                        TransactionManager::startTransaction();

                        TNormal2::create(array('fVarchar'=>"variable characters 444"));

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

                    TNormal2::create(array('fVarchar'=>"variable characters 555"));

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
                    'F_VARCHAR' => $this->getDatasetValue("t_normal2",0,"F_VARCHAR")
                ),
                array(
                    'F_PRIMARY_ID' => 2,
                    'F_VARCHAR' => $this->getDatasetValue("t_normal2",1,"F_VARCHAR")
                ),
                array(
                    'F_PRIMARY_ID' => 3,
                    'F_VARCHAR' => 'variable characters 111'
                ),
                array(
                    'F_PRIMARY_ID' => 4,
                    'F_VARCHAR' => 'variable characters 222'
                ),
                array(
                    'F_PRIMARY_ID' => 7,
                    'F_VARCHAR' => 'variable characters 555'
                )
            )
        ),'SELECT * FROM t_normal2 ');

    }

}