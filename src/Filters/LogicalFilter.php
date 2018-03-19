<?php

namespace Hamba\QueryGet\Filters;

trait LogicalFilter{    
    protected static function createFilterBool($key, $table){
        return self::createFilterFlag($key, $table);
    }
    protected static function createFilterFlag($key, $table)
    {
        $qualifiedColumnName = $table.'.'.$key;
        return function ($query, $value) use ($qualifiedColumnName) {
            switch($value){
                case 'null:':
                    $query->whereNull($qualifiedColumnName);
                    break;
                case 'notnull:':
                    $query->whereNotNull($qualifiedColumnName);
                    break;
                case false:
                case 'false':
                case 'no':
                    $query->where($qualifiedColumnName, 'false');
                    break;
                case 1: 
                case true:
                case 'true':
                case'yes':
                    $query->where($qualifiedColumnName, '1');
                    break;
                default:
                    $query->where($qualifiedColumnName, 0);
                
            }
        };
    }
}