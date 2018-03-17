<?php

namespace Hamba\QueryGet\Filters;

trait StringFilter{
    protected static function createFilterText($key){
        return self::createFilterString($key);
    }

    /**
     * Filter case insensitive string&text
     */
    protected static function createFilterString($key)
    {
        return function ($query, $value) use ($key) {
            //check if array
            if (empty($value)) {
                //ignore filter
            } elseif (is_array($value)) {
                foreach ($value as $val) {
                }
            } else {
                //plain value
                $lowerValue = $value;
                if (is_string($value)) {
                    $lowerValue = strtolower($value);
                }
                if ($value == 'null:') {
                    $query->whereNull($key);
                } elseif ($value == 'notnull:') {
                    $query->whereNotNull($key);
                } elseif ($value == 'empty:') {
                    $query->where($key, '');
                } elseif (starts_with($lowerValue, 'not:')) {
                    $lowerValue = str_replace_start('not:', '', $lowerValue);
                    $query->whereRaw('LOWER('.$key.') NOT LIKE ?', [$lowerValue]);
                } elseif (starts_with($lowerValue, 'like:')) {
                    $lowerValue = str_replace_start('like:', '', $lowerValue);
                    $query->whereRaw('LOWER('.$key.') LIKE ?', [$lowerValue]);
                } else {
                    $query->whereRaw('LOWER('.$key.') LIKE ?', [$lowerValue]);
                }
            }
        };
    }
}