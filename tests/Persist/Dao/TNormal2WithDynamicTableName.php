<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Tests\Persist\Dao;

use PhpPlatform\Persist\Model;

/**
 * @prefix TNormal2
 */
class TNormal2WithDynamicTableName extends Model {
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
    
    /**
     * @columnName F_BOOLEAN
     * @type boolean
     * @set
     * @get
     */
    private $fBoolean = null;


    function __construct($fPrimaryId = null, $fBoolean = null){
        $this->fPrimaryId = $fPrimaryId;
        $this->fBoolean = $fBoolean;
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
    
    
    static function generateTableName(){
    	return 't_normal2';
    }

}
?>
