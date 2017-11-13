<?php

namespace Hamba\QueryGet;

trait Filterable
{
    use Filters\TextFilter;
    use Filters\NumberFilter;
    use Filters\LogicalFilter;
    use Filters\DateTimeFilter;
    use Filters\GISFilter;

    /**
     * Get normalized filter specs
     */
    private static function getNormalizedFilterSpecs(){
        $normalizedFilterSpecs = [];
        $classObj = new static;
        $className = get_class($classObj);
        
        //get original specs
        if (!property_exists($className, 'filterable')) {
            throw new \Exception($className.' is not filterable');
        }
        $specs = $classObj->filterable;

        //validate original specs
        if(!is_array($specs)){
            throw new \Exception('Mallformed filterable for '.$className);
        }

        //begin normalize
        $normalizedFilterSpecs = [];
        foreach ($specs as $key => $spec) {
            if (is_numeric($key)) {
                //there is no setting, just key
                $key = $spec;
                $spec = [
                    'plain',
                    $key
                ];
            }else if(is_string($spec)){
                $specParamPos = strpos($spec, ':');
                if(!$specParamPos){
                    //append column name/relationname
                    $spec = [
                        $spec, //mode
                        $key //prop/relation name
                    ];
                }else{
                    $spec = [
                        substr($spec, 0, $specParamPos), //mode
                        substr($spec, $specParamPos + 1) //prop name
                    ];
                }
            }

            $normalizedFilterSpecs[$key] = $spec;
        }

        return $normalizedFilterSpecs;
    }

    /**
     * Create filter by key
     */
    public static function createFilter($key){
        //find key used in this model (remove relation keys)
        $selfkey = strstr($key, '$', true);
        if(!$selfkey){
            $selfkey = $key;
        }
        $childkey = substr($key, strlen($selfkey) + 1);

        //find match spec
        $filterSpecs = self::getNormalizedFilterSpecs();
        if(!array_key_exists($selfkey, $filterSpecs)){
            //no filter
            return null;
        }

        //parse spec
        $keySpec = $filterSpecs[$selfkey];
        $mode = $keySpec[0];
        $propName = $keySpec[1];

        //parse
        switch ($mode) {
            case 'in':
                //filter value many
                return function ($query, $value) use ($propName) {
                    $query->whereIn($propName, $value);
                };
            case 'plain':
            case null:
                //filter raw
                return self::createFilterPlain($propName);
            case 'relation':
            case 'rel':
                //filter relation
                return self::createFilterRelation($propName, $childkey);
            default:
                $classObj = new static;

                //find filter create function
                $filterCreatorName = 'createFilter'.studly_case($key);
                if(method_exists($classObj, $filterCreatorName)){
                    //filter func is exists
                    return $classObj->$filterCreatorName($key);
                }

                //try find custom filter
                return self::createCustomFilter($classObj, $key);
        }
    }

    protected static function createFilterPlain($key)
    {
        return function ($query, $value) use ($key) {
            $query->where($key, $value);
        };
    }

    public static function createFilterRelation($relationName, $childkey)
    {
        $classObj = (new static);
        $className = get_class($classObj);

        //find relation
        if (!method_exists($className, $relationName)) {
            throw new \Exception('Filter error. '.$className.' has no relation '.$relationName);
        }

        $relation = $classObj->$relationName();
        $relationClass = get_class($relation->getRelated());
        
        if($childkey === false){
            //only filter relation existance
            return function($query, $value) use ($relationName){
                if(($value === true) || ($value === 'true')){
                    $query->whereHas($relationName);
                }else{
                    $query->whereDoesntHave($relationName);
                }
            };
        }else{
            //delegate filter to relation
            $filter = $relationClass::createFilter($childkey);
            if($filter){
                return function($query, $value) use ($relationName, $filter){
                    $query->whereHas($relationName, function ($query) use ($value, $filter) {
                        $filter($query, $value);
                    });
                };
            }

        }
        
        //no filter
        return null;
    }

    protected static function createCustomFilter($classObj, $key)
    {
        $filterFunc = 'filter'.studly_case($key);
        if(method_exists($classObj, $filterFunc)){
            //filter func is exists
            return $classObj->$filterFunc;
        }
        return null;
    }
}
