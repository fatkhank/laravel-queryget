<?php

namespace Hamba\QueryGet\Concerns;

use QG;

trait HandleSort
{
    private $default_sort;
	
	/**
     * Set default sort if not specified in request
     *
     * @param array $opt accept:only, except
     * @return void
     */
	public function defaultSort($sorts){
		$this->default_sort = $sorts;
		return $this;//for chaining
	}

    /**
     * Perform sort to query
     * @param array,string $sortsToApply list of sort to be applied
     * @param array $opt accept:only, except
     * @return void
     */
    public function sort($sortsToApply = null, $opt = [])
    {
        //read context
        $className = $this->model;
        $classObj = self::getInstance($className);
        $mapping = self::getSortMapping($className);

        //get requested sort if not specified
        if(!$sortsToApply){
            $sortsToApply = request("sortby", request("sorts"));
        }
        //if request not exists, use default sort
        if(!$sortsToApply && !$this->default_sort){
            $sortsToApply = $this->default_sort;
        }
        //nothing to sort
        if(!$sortsToApply){return $this;}

        $sortsToApply = QG::normalizeList($sortsToApply);
        foreach ($sortsToApply as $aliasedSort) {
            //decide direction, default is ascending
            $dir="asc";
            if (ends_with($aliasedSort, "_desc")) {
                $aliasedSort = str_replace_last('_desc', '', $aliasedSort);
                $dir = "desc";
            } elseif (ends_with($aliasedSort, "_asc")) {
                $aliasedSort = str_replace_last('_asc', '', $aliasedSort);
            }

            //find mapping
            $unaliasedSort = array_get($mapping, $aliasedSort);
            if($unaliasedSort){
                //check if has override sort function
                $overrideFunc = 'sortBy'.studly_case($unaliasedSort);
                if(isset($classObj) && method_exists($classObj, $overrideFunc)){
                    $classObj->$overrideFunc($this->query, $dir);
                }else{                        
                    //sort by attribute name
                    $this->query->orderBy($unaliasedSort, $dir);
                }
                continue;
            }

            //check if sort by relations
            if(str_contains($aliasedSort, '.')){
                //parse relations
                $joins = substr($aliasedSort, 0, strripos($aliasedSort, '.'));
                if(!empty($joins)){
                    $joinAlias = $this->leftJoin($joins);
                    if($joinAlias){
                        $lastSortPart = substr($aliasedSort, strlen($joins)+1);
                        $sort = $joinAlias.'.'.$lastSortPart;
                        $this->query->orderBy($sort, $dir);
                    }
                }
            }
        }

        //for chaining
        return $this;
    }

    public static function getSortMapping($className, $opt = []){
        //get from queryables
        $queryableCollection = [];
        if(method_exists($className, 'collectNormalizedQueryables')){
            $queryableCollection = $className::collectNormalizedQueryables('key');
        }
        
        //get from sortables
        $classObj = self::getInstance($className);
        $sortables = $classObj->sortable;

        //merge mapping
        $merged = $queryableCollection->merge($sortables);

        //make specs uniform
        $merged = $merged->mapWithKeys(function ($sort, $key) {
            if (is_numeric($key)) {
                return [$sort=>$sort];
            } else {
                return [$key=>$sort];
            }
        });

        if(array_key_exists('only', $opt)){
            $only = QG::normalizeList($opt['only']);
            $merged = QG::filterOnly($merged, $only);
        }else if(array_key_exists('except', $opt)){
            $except = QG::normalizeList($opt['except']);
            $merged = QG::filterExcept($merged, $except);
        }

        //unwrap
        return $merged->toArray();
    }
}
