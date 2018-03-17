<?php

namespace Hamba\QueryGet\Filters;

trait NumberFilter{    
    protected static function createFilterNumber($key)
    {
        return function ($query, $value) use ($key) {
            if ($value == 'null:') {
                $query->whereNull($key);
            } elseif ($value == 'notnull:') {
                $query->whereNotNull($key);
            }elseif(starts_with($key, 'lt:')){
                $query->where($key, '<', str_replace_first('lt:', '', $value));
            }elseif(starts_with($key, 'lte:')){
                $query->where($key, '<=', str_replace_first('lte:', '', $value));
            }elseif(starts_with($key, 'gt:')){
                $query->where($key, '>', str_replace_first('gt:', '', $value));
            }elseif(starts_with($key, 'gte:')){
                $query->where($key, '>=', str_replace_first('gte:', '', $value));
            }else{
                $query->where($key, $value);
            }
        };
    }
}