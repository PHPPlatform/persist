<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Tests\Persist\Dao;


/**
 * @tableName t_parent
 * @prefix TParent
 */
class TParent extends TSuperParent {
    /**
     * @columnName F_PRIMARY_ID
     * @type integer
     * @primary
     * @autoIncrement
     * @get
     */
    private $fPrimaryId = null;

    /**
     * @columnName F_INT
     * @type integer
     * @set
     * @get
     */
    private $fInt = null;

    /**
     * @columnName F_DECIMAL
     * @type decimal
     * @set
     * @get
     */
    private $fDecimal = null;

    /**
     * @columnName F_PARENT_ID
     * @type integer
     * @reference
     * @get
     */
    private $fParentId = null;

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

    static function CreateAccess(){
        return false;
    }

    static function ReadAccess(){
        return false;
    }

    function UpdateAccess(){
        return false;
    }

    function DeleteAccess(){
        return false;
    }

}
?>
