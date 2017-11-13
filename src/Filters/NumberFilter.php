<?php

namespace Hamba\QueryGet\Filters;

trait NumberFilter{    
    protected static function createFilterNumber($key)
    {
        return function ($query, $value) use ($key) {
            $query->where($key, $value);
        };
    }
}