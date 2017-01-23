# PHP Platform Persistance APIs 
This package allows persistence of PHP models (Objects)

[![Build Status](https://travis-ci.org/PHPPlatform/persist.svg?branch=v0.1)](https://travis-ci.org/PHPPlatform/persist)

## Introduction
This php library allows to convert relational schema to php objects

## Features
* Avoids SQL Queries in user source code
* Optimized Queries for all operations
* Dependency Injection
* built in search with sorting and pagination
* Annotations to map class and properties to table and columns
* Supports inheritance

### Example
Sample Class for a Table
```php

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

    static function create( $fVarchar, $fForeign){
        $this->fVarchar = $fVarchar;
        $this->fForeign = $fForeign;
        parent::create();
    }

    static function find($filters){
        return parent::find($filters);
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
```

For More Usage please see the included tests
