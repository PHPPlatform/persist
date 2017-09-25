<?php
/**
 * User: Raaghu
 * Date: 03-09-2015
 * Time: AM 12:03
 */

namespace PhpPlatform\Persist;


interface Constants {

    const VALUE            = "VALUE";

    const OPERATOR_LIKE    = "LIKE";
    const OPERATOR_EQUAL   = "=";
    const OPERATOR_NOT_EQUAL = "!=";
    const OPERATOR_LT      = "<";
    const OPERATOR_GT      = ">";
    const OPERATOR_LTE     = "<=";
    const OPERATOR_GTE     = ">=";
    const OPERATOR_BETWEEN = "BETWEEN";
    const OPERATOR_IN      = "IN";
    const OPERATOR_IS_NULL = "IS NULL";
    const OPERATOR_IS_NOT_NULL = "IS NOT NULL";
    
    const OPERATOR_AND     = "AND";
    const OPERATOR_OR      = "OR";
 
    const SORTBY_ASC       = "ASC";
    const SORTBY_DESC      = "DESC";

}