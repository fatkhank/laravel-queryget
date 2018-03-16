<?php

namespace Hamba\QueryGet\Concerns;

use QG;

trait HandleFilter
{
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
        }

        //create model instance
        $className = $this->model;
        $classObj = self::getInstance($className);
        
        //list applicable filters according to specs
        $applicableFilters = [];
        foreach ($filtersToApply as $key => $value) {
            //parse disjunctions
            $subKeys = explode('_or_', $key);
            foreach ($subKeys as $subkey) {
                $filter = $className::createFilter($subkey, $classObj);
                if($filter){
                    $applicableFilters[$subkey] = $filter;
                }
            }
        }

        $qg = $this;

        //do filter
        foreach ($filtersToApply as $key => $value) {
            //null means no filter, for filtering null, use magic string like :null
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
}
