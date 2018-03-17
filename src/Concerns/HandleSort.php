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
     * Sort only specific sortable
     *
     * @param array $only array of sortable alias included
     * @return void
     */
    public function sortOnly($only){
        return $this->sort(null, [
            'only' => $only
        ]);
    }

    /**
     * Sort except specific sortable
     *
     * @param array $except array of sortable alias excluded
     * @return void
     */
    public function sortExcept($except){
        return $this->sort(null, [
            'except' => $except
        ]);
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
        foreach ($sortsToApply as $alias) {
            //decide direction, default is ascending
            $dir="asc";
            if (ends_with($alias, "_desc")) {
                $alias = str_replace_last('_desc', '', $alias);
                $dir = "desc";
            } elseif (ends_with($alias, "_asc")) {
                $alias = str_replace_last('_asc', '', $alias);
            }

            //find mapping
            $sort = array_get($mapping, $alias);
            if($sort){
                //check if has override sort function
                $overrideFunc = 'sortBy'.studly_case($sort);
                if(isset($classObj) && method_exists($classObj, $overrideFunc)){
                    $classObj->$overrideFunc($this->query, $dir);
                }else{                        
                    //sort by attribute name
                    $this->query->orderBy($sort, $dir);
                }
                continue;
            }

            //check if sort by relations
            if(str_contains($alias, '.')){
                //parse relations
                $joins = substr($alias, 0, strripos($alias, '.'));
                if(!empty($joins)){
                    $joinAlias = $this->leftJoin($joins);
                    if($joinAlias){
                        $lastSortPart = substr($alias, strlen($joins)+1);
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
        $merged = $merged->mapWithKeys(function ($sort, $alias) {
            if (is_numeric($alias)) {
                return [$sort=>$sort];
            } else {
                return [$alias=>$sort];
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
