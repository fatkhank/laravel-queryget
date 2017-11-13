<?php

namespace Hamba\QueryGet\Filters;

trait GISFilter{    
    protected static function createFilterPoint($key)
    {
        return function ($query, $value) use ($key) {
            if($value === ':null'){
                $query->whereNull($key);
            }else if($value === ':notnull'){
                $query->whereNotNull($key);
            }else if(is_array($value)){
                //todo add filter
                if(array_has($value, 'near')){

                }
            }
        };
    }
}