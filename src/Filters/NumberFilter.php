<?php

namespace Hamba\QueryGet\Filters;

trait NumberFilter{    
    protected static function createFilterNumber($key, $table)
    {
        $qualifiedColumnName = $table.'.'.$key;
        return function ($query, $value) use ($qualifiedColumnName) {
            if ($value == 'null:') {
                $query->whereNull($qualifiedColumnName);
            } elseif ($value == 'notnull:') {
                $query->whereNotNull($qualifiedColumnName);
            }elseif(starts_with($value, 'lt:')){
                $query->where($qualifiedColumnName, '<', str_replace_first('lt:', '', $value));
            }elseif(starts_with($value, 'lte:')){
                $query->where($qualifiedColumnName, '<=', str_replace_first('lte:', '', $value));
            }elseif(starts_with($value, 'gt:')){
                $query->where($qualifiedColumnName, '>', str_replace_first('gt:', '', $value));
            }elseif(starts_with($value, 'gte:')){
                $query->where($qualifiedColumnName, '>=', str_replace_first('gte:', '', $value));
            }else{
                $query->where($qualifiedColumnName, $value);
            }
        };
    }
}