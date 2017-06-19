<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Tests\Persist\Dao;


/**
 * @tableName t_child1
 * @prefix TChild1
 */
class TChild1 extends TParent {
    /**
     * @columnName F_PRIMARY_ID
     * @type integer
     * @primary
     * @autoIncrement
     * @get
     */
    private $fPrimaryId = null;

    /**
     * @columnName F_TIMESTAMP
     * @type timestamp
     * @get
     */
    private $fTimestamp = null;

    /**
     * @columnName F_PARENT_ID
     * @type integer
     * @reference
     * @get
     */
    private $fParentId = null;


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
