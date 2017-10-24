<?php

namespace Hamba\QueryTools;

trait Filterable
{
    /**
     * Recursively get filters functions.
     *
     * @param [type] $classOrFilters
     * @param array $opt
     * @return void
     */
    public static function getFilters($opt = [])
    {
        //if use class
        $classObj = new static;
        $className = get_class($classObj);
        if (!property_exists($className, 'filterable')) {
            throw new \Exception($className.' is not filterable');
        }

        //get available filters
        $filterSpecs = $classObj->filterable;

        //validate filterspecs
        if(!is_array($filterSpecs)){
            throw new \Exception('Mallformed filterable for '.$className);
        }

        //filter specs from option
        if (array_has($opt, 'only')) {
            $filterSpecs = $filterSpecs->only($opt['only']);
        } elseif (array_has($opt, 'except')) {
            $filterSpecs = $filterSpecs->except($opt['except']);
        }

        //final filter
        $filters = [];
        foreach ($filterSpecs as $key => $spec) {
            //default, key is property name
            $propName = $key;
            //if not array, setting is just mode
            $mode = $spec;
            
            //parse setting
            if (is_array($spec)) {
                //advance setting
                $propName = $spec[0];
                $mode = $spec[1];
            }

            if (is_numeric($key)) {
                //there is no setting, just key
                $key = $spec;
                $mode = null;
                $propName = $key;
            }

            //parse
            switch ($mode) {
                case 'string':
                    //filter case insensitive string
                    $filters[$key] = self::createFilterString($propName);
                    break;
                case 'in':
                    //filter value many
                    $filters[$key] = function ($query, $value) use ($propName) {
                        $query->whereIn($propName, $value);
                    };
                    break;
                case 'time':
                case 'date':
                case 'datetime':
                    $filters[$key] = self::createFilterDateTime($propName);
                    break;
                case 'datemin':
                case 'date_min':
                case 'mindate':
                    $filters[$key] = self::createFilterDateTime($propName, '>=');
                    break;
                case 'datemax':
                case 'date_max':
                case 'maxdate':
                    $filters[$key] = self::createFilterDateTime($propName, '<=');
                    break;
                case 'number':
                    $filters[$key] = self::createFilterNumber($propName);
                    break;
                case 'boolean':
                case 'bool':
                case 'flag':
                    $filters[$key] = self::createFilterFlag($propName);
                    break;
                case 'plain':
                case null:
                    //filter raw
                    $filters[$key] = self::createFilterPlain($propName);
                    break;
                case 'text':
                    //filter case insensitive string
                    $filters[$key] = self::createFilterString($propName);
                    break;
                case 'point':
                    //filter coordinate
                    $filters[$key] = self::createFilterPoint($propName);
                    break;
                default:
                    //try find custom filter
                    if(isset($classObj)){
                        $filter = self::createFilter($classObj, $key);
                        if($filter){
                            //if has custom filter, use it
                            $filters[$key] = $filter;
                            continue;
                        }
                    }
                    
                    //if filter func not found, meybe its relation
                    $filters = array_merge($filters, self::createFilterRelation($key, $mode, $propName, $opt));
            }
        }

        return $filters;
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

    protected static function createFilterPlain($key)
    {
        return function ($query, $value) use ($key) {
            $query->where($key, $value);
        };
    }

    protected static function createFilterNumber($key)
    {
        return function ($query, $value) use ($key) {
            $query->where($key, $value);
        };
    }

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

    public static function createFilterRelation($prefix, $model, $relationName, $opt = ['depth'=>3])
    {
        //reduce depth
        if (!isset($opt['depth'])) {
            $opt['depth'] = 5;
        }
        $finalFilters = [];

        //if 
        if($opt['depth']-- > 0){
            $modelFilters = self::getFilters($opt);
            foreach ($modelFilters as $modelKey => $modelFilter) {
                $finalFilters[$prefix.'$'.$modelKey] =  function ($query, $value) use ($relationName, $modelFilter) {
                    $query->whereHas($relationName, function ($query) use ($value, $modelFilter) {
                        $modelFilter($query, $value);
                    });
                };
            }
        }
        return $finalFilters;
    }

    protected static function createFilterFlag($key)
    {
        return function ($query, $value) use ($key) {
            switch($value){
                case ':null':
                    $query->whereNull($key);
                    break;
                case ':notnull':
                    $query->whereNotNull($key);
                    break;
                case false:
                case 'false':
                case 'no':
                    $query->where($key, 'false');
                    break;
                case 1: 
                case true:
                case 'true':
                case'yes':
                    $query->where($key, '1');
                    break;
                default:
                    $query->where($key, 0);
                
            }
        };
    }

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

    protected static function createFilter($classObj, $key)
    {
        $filterFunc = 'filter'.studly_case($key);
        if(method_exists($classObj, $filterFunc)){
            //filter func is exists
            return $classObj->$filterFunc;
        }
        return null;
    }
}
