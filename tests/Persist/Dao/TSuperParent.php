<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Tests\Persist\Dao;


use PhpPlatform\Persist\Model;

/**
 * @tableName t_super_parent
 * @prefix TSuperParent
 */
class TSuperParent extends Model {
    /**
     * @columnName F_PRIMARY_ID
     * @type integer
     * @primary
     * @autoIncrement
     * @get
     */
    private $fPrimaryId = null;

    /**
     * @columnName F_VARCHAR
     * @type varchar
     * @set
     * @get
     */
    private $fVarchar = null;


    function __construct($fPrimaryId = null){
        if($this->fPrimaryId == null){
            $this->fPrimaryId = $fPrimaryId;
        }
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
