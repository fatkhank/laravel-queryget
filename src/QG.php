<?php

namespace Hamba\QueryGet;
use DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class QG{
    protected $model;
    public $query;
    private static $wrappers = [];
    private static $instanceCache = [];

    public function __construct($modelOrQuery, $query = null){
        if($modelOrQuery instanceof \Illuminate\Database\Eloquent\Builder){
            //if first param is query
            $this->query = $modelOrQuery;
            $this->model = get_class($modelOrQuery->getModel());
        }else{
            //if first param is class
            $this->model = $modelOrQuery;
            //get query
            if($query){
                //if query provided, use it
                $this->query = $query;
            }else{
                //if query not provided, create new from model
                $this->query = $modelOrQuery::query();
            }
        }
    }

    public static function getInstance($className){
        if(array_key_exists($className, self::$instanceCache)){
            return self::$instanceCache[$className];
        }
        return (self::$instanceCache[$className] = new $className);
    }

    /**
     * Perform selection to query.
     *
     * @param array $opt accept:only,except
     * @return void
     */
    public function select($selections = null, $opt = null)
    {
        //reset option
        if (!$opt) {
            $opt = [];
        }
        
        //set maximum recursive depth
        $opt['depth'] = 1;

        //if select not specified, select from request
        if(!$selections){
            $selections = request('props');
        }

        if($selections){
            if(is_string($selections)){
                //if props is concatenated string, parse it
                $selections = explode(',', $selections);
            }
        }else{
            //if no requested selects, default to select all without relation
            $selections = ['*'];
        }

        //make only & except option to nested array (instead of flat array with dot notation)
        if(array_key_exists('only', $opt)){
            $flatOnly = array_sort($opt['only']);
            $unflattedOnly = [];
            foreach ($flatOnly as $value) {
                data_set($unflattedOnly, $value, false);
            }
            $opt['only'] = $unflattedOnly;
        }else if(array_key_exists('except', $opt)){
            $flatExcept = array_sort($opt['except']);
            $unflattedexcept = [];
            foreach ($flatExcept as $value) {
                data_set($unflattedexcept, $value, false);
            }
            $opt['except'] = $unflattedexcept;
        }

        //get specification
        $className = $this->model;
        //check if class is selectable
        if (!method_exists($className, 'getSelects')) {
            throw new \Exception($className.' is not selectable');
        }

        //parse selection to be applicable for selects() and with()
        $parsedSelect = $className::getSelects($selections, $opt);
        //process selection
        $this->recursiveSelect($this->query, $parsedSelect['selects'], $parsedSelect['withs']);
        //for chaining
        return $this;
    }
    
    /**
     * Apply select and lazy load to query
     *
     * @param [type] $query
     * @param [type] $selects
     * @param [type] $withs
     * @param integer $depth
     * @return void
     */
    private function recursiveSelect($query, $selects, $withs, $depth = 5)
    {
        //apply select
        if ($selects !== null) {
            $query->select($selects);
        }

        //skip with if depth zero
        if ($depth == 0) {
            return;
        }

        //skip if no withs
        if (!$withs) {
            return;
        }

        //do recursive select
        $applicableWiths = [];
        foreach ($withs as $key => $prop) {
            $withName = array_get($prop, 'name', $key);
            $withSelects = array_get($prop, 'selects', []);
            $withWiths = array_get($prop, 'withs', []);
            $applicableWiths[$withName] = function ($query) use ($withSelects, $withWiths, $depth) {
                return $this->recursiveSelect($query, $withSelects, $withWiths, $depth - 1);
            };
        }
        
        //apply withs
        $query->with($applicableWiths);
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
                $this->query->where(function ($query) use ($subKeys, $value, $applicableFilters) {
                    foreach ($subKeys as $subkey) {
                        $filter = array_get($applicableFilters,$subkey);
                        if($filter){
                            //apply filter if exists
                            $query->orWhere(function ($query) use ($filter, $value) {
                                $filter($query, $value);
                            });
                        }
                    }
                });
            } else {
                //no disjunction
                $filter = array_get($applicableFilters,$key);
                if($filter){
                    //apply filter if exist
                    $filter($this->query, $value);
                }
            }
        }

        //for chaining
        return $this;
    }
	
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
        //it is class
        $className = $this->model;
        $classObj = self::getInstance($className);
        $specifications = self::getSortSpecs($className);

        //get requested sort if not specified
        if(!$sortsToApply){
            $sortsToApply = request("sortby", request("sorts"));
        }
        if($sortsToApply){
            if(is_string($sortsToApply)){
                //split if sort is concatenated attributes
                $sortsToApply = explode(',', $sortsToApply);
            }
        }else{
            //if no sort requested, sort use default sort
            if($this->default_sort){
                $sortsToApply = $this->default_sort;
            }else{
                //no sort
                return $this;
            }
        }

        //wrap in array
        $sortsToApply = array_wrap($sortsToApply);
        foreach ($sortsToApply as $appliedSort) {
            $dir="asc";//default is ascending
            
            //decide direction
            if (ends_with($appliedSort, "_desc")) {
                $appliedSort = substr($appliedSort, 0, count($appliedSort) - 6);
                $dir = "desc";
            } elseif (ends_with($appliedSort, "_asc")) {
                $appliedSort = substr($appliedSort, 0, count($appliedSort) - 5);
            }

            //check if there are required joins
            if(!str_contains($appliedSort, '.')){
                if (array_has($specifications, $appliedSort)) {
                    //sort available
                    $sort = array_get($specifications, $appliedSort);
                    $overrideFunc = 'sortBy'.studly_case($sort);
    
                    //check if has override sort function                
                    if(isset($classObj) && method_exists($classObj, $overrideFunc)){
                        $classObj->$overrideFunc($this->query, $dir);
                    }else{                        
                        //sort by attribute name
                        $this->query->orderBy($sort, $dir);
                    }
                }
            }else{
                $joins = str_before($appliedSort, '.');
                $joinAlias = $this->leftJoin($joins);
                if($joinAlias){
                    $lastSortPart = substr($appliedSort, strlen($joins)+1);
                    $sort = $joinAlias.'.'.$lastSortPart;
                    $this->query->orderBy($sort, $dir);
                }
            }
        }

        //for chaining
        return $this;
    }

    public static function getSortSpecs($className, $opt = []){
        //get from queryables
        $queryableSpecs = [];
        if(method_exists($className, 'getNormalizedQueryables')){
            $queryableSpecs = $className::getNormalizedQueryables(function($type, $realName, $queryability){
                if(
                    str_contains($queryability, '|sort|') ||
                    str_contains($queryability, '|all|')
                ){
                    return $realName;
                }

                return false;
            });
        }
        
        //get from sortables
        $classObj = self::getInstance($className);
        $sortableSpecs = $classObj->sortable;

        //merge specifications
        $finalSpecs = collect($queryableSpecs)->merge($sortableSpecs);

        //make specs uniform
        $finalSpecs = $finalSpecs->mapWithKeys(function ($sort, $key) {
            if (is_numeric($key)) {
                return [$sort=>$sort];
            } else {
                return [$key=>$sort];
            }
        });

        //filter specs from option
        if (array_has($opt, 'only')) {
            $finalSpecs = $finalSpecs->only($opt['only']);
        } elseif (array_has($opt, 'except')) {
            $finalSpecs = $finalSpecs->except($opt['except']);
        }

        //unwrap
        return $finalSpecs->toArray();
    }

    /**
     * Apply paginate to query
     *
     * @return void
     */
    public function paginate()
    {
        $skip = intval(max(request("skip", 0), 0));
        $count = request("count", 10);

        $this->query->skip($skip)->take($count);

        //for chaining
        return $this;
    }

    /**
     * Apply filters, select and sort
     *
     * @return void
     */
    public function apply(){
        return $this
            ->filter()
            ->select()
            ->sort();
    }

    /**
     * Get query results, with optional wrap
     *
     * @param string $wrap wrap mode
     * @return mixed
     */
    public function get($wrap = 'default', $wrapperParam = null)
    {
        //paginate param
        $skip = intval(max(request("skip", 0), 0));
        $count = request("count", 10);

        //total results count
        $total = $this->query->count();

        //query results
        $data = [];

        $this->paginate();

        //run query only if necessary
        if ($count > 0) {
            $data = $this->query->get();
        }

        //wrap result
        if($wrap === false){
            //no wrap
            return $data;
        }else if($wrap === 'default'){
            //wrap default
            return response()->json([
                'total' => $total,
                'data_count' => count($data),
                'data' => $data,
            ]);
        }else{
            $meta = [
                'total' => $total,
                'skip' => $skip,
                'count' => $count
            ];

            if(is_callable($wrap)){
                //if wrap function provided
                return response()->json($wrap($data, $meta, $wrapperParam));
            }else if(array_key_exists($wrap, self::$wrappers)){
                //find in wrapper collections
                return self::$wrappers[$wrap]($data, $meta, $wrapperParam);
            }

            //
            throw new \Exception('Wrap not supported');
        }
    }

    public static function addWrapper($name, $callback){
        self::$wrappers[$name] = $callback;
    }

    /**
     * Proxy to call query->firstOrFail()
     *
     * @return void
     */
    public function fof(){
        return $this->query->firstOrFail();
    }

    /**
     * Proxy to call query->first()
     *
     * @return void
     */
    public function one(){
        return $this->query->first();
    }

    /**
     * Proxy to call query->find()
     *
     * @param mixed $id
     * @return void
     */
    public function id($id){
        return $this->query->find($id);
    }

    /**
     * Proxy to call query->find()
     *
     * @param mixed $id
     * @return void
     */
    public function idOrFail($id){
        return $this->query->findOrFail($id);
    }

    protected $joined = [];
    public function leftJoin($join){
        //prevent duplicate join
        $cache = $this->joined;
        if(array_key_exists($join, $cache)){
            return $joined[$join]['alias'];
        }

        //save last join alias
        $joinAlias = null;
        $sourceModel = $this->model;
        $splittedJoins = explode('.', $join);
        foreach ($splittedJoins as $splittedJoin) {
            $final = $this->singleLeftJoin($sourceModel, $splittedJoin, $joinAlias);
            if(!$final){
                //fail to join
                return null;
            }
            $joinAlias = $final['alias'];
            $sourceModel = $final['class'];
        }

        return $joinAlias;
    }

    private function singleLeftJoin($sourceModel, $join, $prefix){
        $cache = $this->joined;

        //prevent duplicate join
        if(array_key_exists($join, $cache)){
            return $joined[$join];
        }

        //get specification
        $classObj = self::getInstance($sourceModel);
        //$relationName = studly_case($join);
        $relationName = $join;
        $relation = $classObj->$relationName();

        //relation not found
        if(!$relation){
            //todo find custom join method
            return null;
        }

        if(!$prefix){
            $prefix = $classObj->getTable();
        }
        
        //parse relation
        $relatedClass = $relation->getRelated();
        $relatedTbl = $relatedClass->getTable();
        $foreignKey = $relation->getForeignKey();
        $ownedKey = $relation->getOwnerKey();

        if ($relation instanceof BelongsTo) {
            $joinAlias = $relationName.'_join';
            //belongsTo need foreign
            $this->query->leftJoin($relatedTbl.' as '.$joinAlias, $joinAlias.'.'.$ownedKey, '=', $prefix.'.'.$foreignKey);
        }

        return ($joined[$join] = [
            'class' => $relatedClass,
            'alias' => $joinAlias,
        ]);
    }
}