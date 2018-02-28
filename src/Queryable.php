<?php

namespace Hamba\QueryGet;

trait Queryable
{
    use Filterable;
    use Selectable;
    use Sortable;

    /**
     * Get normalized queryables
     */
    public static function getNormalizedQueryables($filter = null){
        $classObj = new static;
        $className = get_class($classObj);

        //empty
        if (!property_exists($classObj, 'queryable')) {
            return [];
        }

        // queryable format = 
        // [
        //      key1 => 'type1:realKey1;queryability1',
        //      key2 => 'type2:realKey2;queryability2'
        // ]
        // 
        $queryables = $classObj->queryable;

        //begin normalize each spec
        $normalizedQueryables = [];
        foreach ($queryables as $key => $qVal) {
            //1. normalize value for key only
            if (is_numeric($key)) {
                $key = $qVal;
                $qVal = 'null:'.$key.';all';
            }

            //2. split queryability and specification
            $delim = strpos($qVal, ';');
            if(!$delim){
                //if queryability not specified, use 'all'
                $spec = $qVal;
                $queryability = '|all|';
            }else{
                $spec = substr($qVal, 0, $delim);
                $queryability = '|'.substr($qVal, $delim+1).'|';
            }
            
            //3. split specType and specName
            $delim = strpos($spec, ':');
            if(!$delim){
                //if realKey not specified
                $specType = $spec;
                $specName = $key;
            }else{
                $specType = substr($spec, 0, $delim);
                $specName = substr($spec, $delim+1);
            }

            //filter by queryability
            if($filter){
                $filterResult = $filter($specType, $specName, $queryability);
                if($filterResult !== false){
                    $normalizedQueryables[$key] = $filterResult;
                }
            }
        }

        return $normalizedQueryables;
    }
}
