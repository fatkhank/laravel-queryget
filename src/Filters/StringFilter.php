<?php

namespace Hamba\QueryTools\Filters;

trait StringFilter{
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
                //make value case insensitive
                $lowerValue = $value;
                if (is_string($value)) {
                    $lowerValue = strtolower($value);
                }
                if ($value == ':null') {
                    $query->whereNull($key);
                } elseif ($value == ':notnull') {
                    $query->whereNotNull($key);
                } elseif ($value == ':empty') {
                    $query->where($key, '');
                } elseif (starts_with($lowerValue, 'not:')) {
                    $lowerValue = substr($lowerValue, 4);
                    $query->whereRaw('LOWER('.$key.') NOT LIKE ?', [$lowerValue]);
                } else {
                    $query->whereRaw('LOWER('.$key.') LIKE ?', [$lowerValue]);
                }
            }
        };
    }
}