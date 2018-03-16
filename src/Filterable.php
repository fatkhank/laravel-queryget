<?php

namespace Hamba\QueryGet;

trait Filterable
{
    use Filters\StringFilter;
    use Filters\NumberFilter;
    use Filters\LogicalFilter;
    use Filters\DateTimeFilter;
    use Filters\GISFilter;

    private static $filterSpecsCache = null;

    /**
     * Get normalized filter specs
     */
    private static function getNormalizedFilterSpecs(){
        //try use cache
        if(self::$filterSpecsCache){
            return self::$filterSpecsCache;
        }

        $classObj = new static;
        $className = get_class($classObj);

        //get from queryables
        $queryableCollection = [];
        if(method_exists($className, 'collectNormalizedQueryables')){
            $queryableCollection = $className::collectNormalizedQueryables('plain');
        }
        
        //get from filterable
        $filterableSpecs = $classObj->filterable;

        //
        $merged = $queryableCollection->merge($filterableSpecs);

        $normalized = $merged->mapWithKeys(function ($spec, $aliasedKey) {
            //make specifications uniform
            if (is_numeric($aliasedKey)) {
                //there is no setting, just key
                $aliasedKey = $spec;
                $spec = [
                    null,
                    $aliasedKey
                ];
            }else if(is_string($spec)){
                $modeDelimPosition = strpos($spec, ':');
                if(!$modeDelimPosition){
                    //append column name/relationname
                    $spec = [
                        $spec, //mode
                        $aliasedKey //prop/relation name
                    ];
                }else{
                    $spec = [
                        substr($spec, 0, $modeDelimPosition), //mode
                        substr($spec, $modeDelimPosition + 1) //prop name
                    ];
                }
            }

            return [$aliasedKey => $spec];
        })->toArray();

        self::$filterSpecsCache = $normalized;
        return $normalized;
    }

    /**
     * Create filter by key
     */
    public static function createFilter($key, $modelInstance){
        //find key used in this model (remove relation keys)
        $selfkey = strstr($key, '$', true);
        if(!$selfkey){
            $selfkey = $key;
        }
        $relationKey = substr($key, strlen($selfkey) + 1);

        //find match spec
        $filterSpecs = self::getNormalizedFilterSpecs();
        if(!array_key_exists($selfkey, $filterSpecs)){
            //no filter
            return null;
        }

        $keySpec = $filterSpecs[$selfkey];
        $type = $keySpec[0];
        $tblName = $modelInstance->getTable();
        $propName = $tblName.'.'.$keySpec[1];

        //parse
        switch ($type) {
            case 'in':
                //filter value many
                return function ($query, $value) use ($propName) {
                    $query->whereIn($propName, $value);
                };
            case 'relation':
            case 'rel':
                //filter relation
                return self::createFilterRelation($propName, $relationKey);
            case 'plain':
                return self::createFilterPlain($propName);
            default:
                $classObj = new static;

                //find filter create function
                $filterCreatorName = 'createFilter'.studly_case($type);
                if(method_exists($classObj, $filterCreatorName)){
                    //filter func is exists
                    return $classObj->$filterCreatorName($propName);
                }

                //try find custom filter
                $customFilter = self::createCustomFilter($classObj, $key);
                if($customFilter){
                    return $customFilter;
                }

                //use raw filter as default
                return self::createFilterPlain($propName);
        }
    }

    protected static function createFilterPlain($key)
    {
        return function ($query, $value) use ($key) {
            $query->where($key, $value);
        };
    }

    public static function createFilterRelation($relationName, $relationKey)
    {
        $classObj = (new static);
        $className = get_class($classObj);

        //find relation
        if (!method_exists($className, $relationName)) {
            throw new \Exception('Filter error. '.$className.' has no relation '.$relationName);
        }

        $relation = $classObj->$relationName();
        $relationClass = get_class($relation->getRelated());
        
        if($relationKey === false){
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
            $filter = $relationClass::createFilter($relationKey);
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
            return function($query, $value) use ($classObj, $filterFunc){
                $classObj->$filterFunc($query, $value);
            };
        }
        return null;
    }
}
