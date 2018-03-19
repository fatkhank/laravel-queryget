<?php

namespace Hamba\QueryGet\Filters;

trait StringFilter{
    protected static function createFilterText($key){
        return self::createFilterString($key);
    }

    /**
     * Filter case insensitive string&text
     */
    protected static function createFilterString($key, $table)
    {
        $qualifiedColumnName = $table.'.'.$key;
        return function ($query, $value) use ($qualifiedColumnName) {
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
                    $query->whereNull($qualifiedColumnName);
                } elseif ($value == 'notnull:') {
                    $query->whereNotNull($qualifiedColumnName);
                } elseif ($value == 'empty:') {
                    $query->where($qualifiedColumnName, '');
                } elseif (starts_with($lowerValue, 'not:')) {
                    $lowerValue = str_replace_first('not:', '', $lowerValue);
                    $query->whereRaw('LOWER('.$qualifiedColumnName.') NOT LIKE ?', [$lowerValue]);
                } elseif (starts_with($lowerValue, 'like:')) {
                    $lowerValue = str_replace_first('like:', '', $lowerValue);
                    $query->whereRaw('LOWER('.$qualifiedColumnName.') LIKE ?', [$lowerValue]);
                } else {
                    $query->whereRaw('LOWER('.$qualifiedColumnName.') LIKE ?', [$lowerValue]);
                }
            }
        };
    }
}