<?php

namespace Hamba\QueryGet\Concerns;

use QG;

trait HandleFilter
{
    /**
     * Perform filters from request. Default filter also will be applied unless overriden in request.
     *
     * @param array $defaults
     * @param array $opt
     * @return void
     */
    public function filterWithDefault($defaults, $opt = []){
        $opt['default'] = array_merge($defaults, array_get($opt, 'default', []));
        return $this->filter(null, $opt);
    }

    /**
     * Perform filter to query.
     *
     * @param array $opt accept:only,except
     * @return void
     */
    public function filter($filtersToApply = null, $opt = [])
    {
        //get filter from request
        if(!$filtersToApply){
            $filtersToApply = request()->all();
        }else{
            //if opt is string, assume usage as filter(key,value)
            if(is_string($filtersToApply)){
                $filtersToApply = [$filtersToApply => $opt];
                $opt = [];
            }
        }

        $defaultFilters = array_get($opt, 'default');
        if($defaultFilters){
            $filtersToApply = array_merge($defaultFilters, $filtersToApply);
        }

        //create model instance
        $className = $this->model;
        $classObj = self::getInstance($className);
        
        //list applicable filters according to specs
        $applicableFilters = [];
        foreach ($filtersToApply as $key => $value) {
            //parse disjunctions
            $filterStrings = explode('_or_', $key);
            foreach ($filterStrings as $substring) {
                $filter = $this->createFilter($substring, $classObj);
                if($filter){
                    $applicableFilters[$substring] = $filter;
                }
            }
        }

        $qg = $this;

        //do filter
        foreach ($filtersToApply as $key => $value) {
            //null means no filter, for filtering null, use magic string like null:
            if ($value === null) {
                continue;
            }

            //parse disjunctions
            $subKeys = explode('_or_', $key);
            
            if (count($subKeys) > 1) {
                //has disjunction
                $this->query->where(function ($query) use ($subKeys, $value, $applicableFilters, $qg) {
                    foreach ($subKeys as $subkey) {
                        $filter = array_get($applicableFilters, $subkey);
                        if($filter){
                            //apply filter if exists
                            $query->orWhere(function ($query) use ($filter, $value, $qg) {
                                $filter($query, $value, $qg);
                            });
                        }
                    }
                });
            } else {
                //no disjunction
                $filter = array_get($applicableFilters,$key);
                if($filter){
                    //apply filter if exist
                    $filter($this->query, $value, $qg);
                }
            }
        }

        //for chaining
        return $this;
    }

    private static $filterMappingCache = [];

    /**
     * Get normalized filter specs
     */
    private static function getNormalizedFilterMapping($className){
        //try use cache
        $cache = array_get(self::$filterMappingCache, $className);
        if($cache){return $cache;}

        $classObj = QG::getInstance($className);

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

        self::$filterMappingCache[$className] = $normalized;
        return $normalized;
    }

    protected static $filterCache = [];

    /**
     * Create filter by filterstring
     *
     * @param string $filterString
     * @param [type] $modelInstance
     * @return void
     */
    public function createFilter($filterString, $modelInstance){
        $className = get_class($modelInstance);

        //try use cache
        $cacheKey = $className.'.'.$filterString;
        $cache = array_get(self::$filterCache, $cacheKey);
        if($cache){return $cache;}
        
        //find alias belongs to this model
        $firstAlias = strstr($filterString, '$', true);//'$' is relation delimiter
        if(!$firstAlias){
            $firstAlias = $filterString;
        }
        
        //find mapping, skip if not exists
        $aliasMapping = self::getNormalizedFilterMapping($className);
        $typeKeyPair = array_get($aliasMapping, $firstAlias);
        if(!$typeKeyPair){return null;}
        
        //get type and key
        $realKey = $typeKeyPair[1];
        $type = $typeKeyPair[0];

        //check if can be traited as relation filter
        if (method_exists($className, $realKey)) {
            $relationFilterString = substr($filterString, strlen($firstAlias) + 1);
            $filter = $this->createFilterRelation($modelInstance, $realKey, $relationFilterString);
        }else{
            //try find custom filter
            $customFilter = $this->createCustomFilter($modelInstance, $realKey);
            if($customFilter){return $customFilter;}
            
            //parse more params to determine matching filter
            $table = $modelInstance->getTable();
            
            //return filter with type
            $filter = $className::createFilter($type, $realKey, $table);
        }
        
        //put to cache
        array_set(self::$filterCache, $cacheKey, $filter);
        return $filter;
    }

    public function createFilterRelation($classObj, $relationName, $filterString)
    {
        $className = get_class($classObj);

        //check relation existence
        if (!method_exists($className, $relationName)) {
            throw new \Exception('Filter error. '.$className.' has no relation '.$relationName);
        }

        $relation = $classObj->$relationName();
        $related = $relation->getRelated();
        $relationClass = get_class($related);
        
        if(empty($filterString)){
            //only filter relation existance
            return function($query, $value) use ($relationName){
                if(($value === true) || ($value === 'true')){
                    $query->whereHas($relationName);
                }else{
                    $query->whereDoesntHave($relationName);
                }
            };
        }else{
            //recursive filter
            $filter = $this->createFilter($filterString, $related);
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

    protected function createCustomFilter($classObj, $key)
    {
        $filterFunc = 'filter'.studly_case($key);
        $qg = $this;
        if(method_exists($classObj, $filterFunc)){
            //filter func is exists
            return function($query, $value) use ($classObj, $filterFunc, $qg){
                $classObj->$filterFunc($query, $value, $qg);
            };
        }
        return null;
    }
}
