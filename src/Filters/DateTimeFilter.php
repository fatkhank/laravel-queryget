<?php

namespace Hamba\QueryGet\Filters;

trait DateTimeFilter{
    protected static function createFilterDate($key){
        return self::createFilterDateTime($key, '=');
    }
    protected static function createFilterTime($key){
        return self::createFilterDateTime($key, '=');
    }

    protected static function createFilterDateMax($key){
        return self::createFilterDateTime($key, '<=');
    }

    protected static function createFilterDateMin($key){
        return self::createFilterDateTime($key, '>=');
    }

    protected static function createFilterDateTime($key, $operator = '=')
    {
        return function ($query, $value) use ($key, $operator) {
            if ($value == ':null') {
                $query->whereNull($key);
            } elseif ($value == ':notnull') {
                $query->whereNotNull($key);
            } elseif (!is_null($value)) {
                $query->where($key, $operator, Carbon::parse($value));
            }
        };
    }
}