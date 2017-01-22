<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Tests\Persist\Dao;

use PhpPlatform\Persist\Model;

/**
 * @tableName t_normal1
 * @prefix TNormal1
 */
class TNormal1 extends Model {
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
     * @columnName F_FOREIGN
     * @type integer
     * @set
     * @get
     */
    private $fForeign = null;


    function __construct($fPrimayId = null){
        $this->fPrimaryId = $fPrimayId;
        parent::__construct();
    }

    function delete(){
        parent::delete();
    }

    function setAttribute($name,$value){
        $args = array();
        $args[$name] = $value;
        $attrValues = $this->setAttributes($args);
    }

    function setAttributes($args){
        parent::setAttributes($args);
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
