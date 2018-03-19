<?php

namespace Hamba\QueryGet\Filters;

trait DateTimeFilter{
    protected static function createFilterDate($key, $table){
        return self::createFilterDateTime($key, $table, '=');
    }
    protected static function createFilterTime($key, $table){
        return self::createFilterDateTime($key, $table, '=');
    }

    protected static function createFilterDateMax($key, $table){
        return self::createFilterDateTime($key, $table, '<=');
    }

    protected static function createFilterDateBefore($key, $table){
        return self::createFilterDateTime($key, $table, '<');
    }

    protected static function createFilterDateMin($key, $table){
        return self::createFilterDateTime($key, $table, '>=');
    }

    protected static function createFilterDateAfter($key, $table){
        return self::createFilterDateTime($key, $table, '>');
    }

    protected static function createFilterDateTime($key, $table, $operator = '=')
    {
        $qualifiedColumnName = $table.'.'.$key;
        return function ($query, $value) use ($qualifiedColumnName, $operator) {
            if ($value == 'null:') {
                $query->whereNull($qualifiedColumnName, $table);
            } elseif ($value == 'notnull:') {
                $query->whereNotNull($qualifiedColumnName, $table);
            } elseif (!is_null($value)) {
                $query->where($qualifiedColumnName, $operator, Carbon::parse($value));
            }
        };
    }
}