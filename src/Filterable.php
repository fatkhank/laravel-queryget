<?php

namespace Hamba\QueryGet;

trait Filterable
{
    use Filters\StringFilter;
    use Filters\NumberFilter;
    use Filters\LogicalFilter;
    use Filters\DateTimeFilter;
    use Filters\GISFilter;

    public static function createFilter($type, $key, $table){
        $filterCreatorName = 'createFilter'.studly_case($type);
        if(method_exists(__CLASS__, $filterCreatorName)){
            return self::$filterCreatorName($key, $table);
        }else{
            return self::createFilterPlain($key, $table);
        }
    }

    protected static function createFilterPlain($key, $table)
    {
        $qualifiedColumnName = $table.'.'.$key;
        return function ($query, $value) use ($qualifiedColumnName) {
            if(is_array($value)){
                $query->whereIn($qualifiedColumnName, $value);
            }else{
                $query->where($qualifiedColumnName, $value);
            }
        };
    }
}
