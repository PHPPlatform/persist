<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Tests\Persist\Dao\Relations;

use PhpPlatform\Persist\Model;

/**
 * @tableName t_many2_to_many3
 * @prefix TMany2ToMany3
 */
class TMany2ToMany3 extends Model {
    /**
     * @columnName F_MANY2_PRIMARY_ID
     * @type integer
     * @set
     * @get
     * @groupBy
     */
    private $fMany2PrimaryId = null;

    /**
     * @columnName F_MANY3_PRIMARY_ID
     * @type integer
     * @set
     * @get
     * @group
     */
    private $fMany3PrimaryId = null;
    
    /**
     * @columnName F_MANY3_PRIMARY_ID
     * @type varchar
     * @foreignField "PhpPlatform\\Tests\\Persist\\Dao\\Relations\\TMany3->fMany3Name"
     * @get
     * @group
     */
    private $fMany3Name = null;
    
    /**
     * @columnName F_MANY3_PRIMARY_ID
     * @type boolean
     * @foreignField "PhpPlatform\\Tests\\Persist\\Dao\\Relations\\TMany3->fMany3Bool"
     * @get
     * @group
     */
    private $fMany3Bool = null;
    

    function __construct($fMany2PrimaryId = null,$fMany3PrimaryId = null){
        $this->fMany2PrimaryId = $fMany2PrimaryId;
        $this->fMany3PrimaryId = $fMany3PrimaryId;
        parent::__construct();
    }

    function delete(){
        parent::delete();
    }

    function setAttribute($name,$value){
        $args = array();
        $args[$name] = $value;
        return $this->setAttributes($args);
    }

    function setAttributes($args){
        return parent::setAttributes($args);
    }

    function getAttribute($name){
        $args = array();
        $args[] = $name;
        $attrValues = $this->getAttributes($args);
        return $attrValues[$name];
    }

    function getAttributes($args){
        return parent::getAttributes($args);
    }

}
?>
