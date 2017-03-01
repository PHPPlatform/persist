<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Tests\Persist\Dao\Relations;

use PhpPlatform\Persist\Model;

/**
 * @tableName t_many3
 * @prefix TMany3
 */
class TMany3 extends Model {
    /**
     * @columnName F_PRIMARY_ID
     * @type integer
     * @primary
     * @autoIncrement
     * @get
     */
    private $fPrimaryId = null;

    /**
     * @columnName F_MANY3_NAME
     * @type varchar
     * @set
     * @get
     */
    private $fMany3Name = null;

    function __construct($fPrimaryId = null){
        $this->fPrimaryId = $fPrimaryId;
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
