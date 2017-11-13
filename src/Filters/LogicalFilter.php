<?php

namespace Hamba\QueryGet\Filters;

trait LogicalFilter{    
    protected static function createFilterBool($key){
        return self::createFilterFlag($key);
    }
    protected static function createFilterFlag($key)
    {
        return function ($query, $value) use ($key) {
            switch($value){
                case ':null':
                    $query->whereNull($key);
                    break;
                case ':notnull':
                    $query->whereNotNull($key);
                    break;
                case false:
                case 'false':
                case 'no':
                    $query->where($key, 'false');
                    break;
                case 1: 
                case true:
                case 'true':
                case'yes':
                    $query->where($key, '1');
                    break;
                default:
                    $query->where($key, 0);
                
            }
        };
    }
}