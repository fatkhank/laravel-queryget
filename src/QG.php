<?php

namespace Hamba\QueryGet;

class QG{
    protected $model;
    public $query;
    private static $wrappers = [];

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

    /**
     * Enable request to select query.
     *
     * @param array $opt accept:only,except
     * @return void
     */
    public function select($opt = null)
    {
        //reset option
        if (!$opt) {
            $opt = [];
        }
        
        //set maximum recursive depth
        $opt['depth'] = 1;

        //get filter from request
        $requestSelects = request('props', array_get($opt, 'default'));
        if (!$requestSelects) {
            //if no requested selects, default to select all without relation
            $requestSelects = ['*'];
        }

        //get specification
        $classOrSelects = $this->model;
        $final = $classOrSelects;
        if (!is_array($classOrSelects)) {
            //its class,
            $className = $classOrSelects;
            
            //check if class is selectable
            if (!method_exists($className, 'getSelects')) {
                throw new \Exception($className.' is not selectable');
            }

            $final = $className::getSelects($requestSelects, $opt);
        }

        //process property
        $this->applySelects($final['selects'], $final['withs']);

        //for chaining
        return $this;
    }
    
    private function applySelects($selects, $withs, $depth = 5)
    {
        //apply select
        if ($selects !== null) {
            $this->query->select($selects);
        }

        //skip with if depth zero
        if ($depth == 0) {
            return;
        }

        //skip if no withs
        if (!$withs) {
            return;
        }

        //parse recursive with
        $applicableWiths = [];
        //parse recursive with
        foreach ($withs as $key => $prop) {
            $withName = array_get($prop, 'name', $key);
            $withSelects = array_get($prop, 'selects', []);
            $withWiths = array_get($prop, 'withs', []);
            $applicableWiths[$withName] = function ($query) use ($withSelects, $withWiths, $depth) {
                return $this->applySelects($withSelects, $withWiths, $depth - 1);
            };
        }
        
        //apply withs
        $this->query->with($applicableWiths);
    }

     /**
     * Enable request to filter query.
     *
     * @param array $opt accept:only,except
     * @return void
     */
    public function applyFilter($opt = [])
    {
        //get filter from request
        $requestFilters = request()->all();

        //create model instance
        $className = $this->model;
        $classObj = new $className;
        
        //applicable filters, mapped by key
        $applicableFilters = [];

        //filter only requested level
        foreach ($requestFilters as $key => $value) {
            //parse disjunctions
            $subKeys = explode('_or_', $key);
            foreach ($subKeys as $subkey) {
                $filter = $className::createFilter($subkey);
                if($filter){
                    $applicableFilters[$subkey] = $filter;
                }
            }
        }

        //do filter
        foreach ($requestFilters as $key => $value) {
            //null means no filter, for filtering null, use magic string like :null
            if ($value === null) {
                continue;
            }

            //parse disjunctions
            $subKeys = explode('_or_', $key);
            
            //no filter for this one, ignore
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
     * Set default sort
     *
     * @param array $opt accept:only, except
     * @return void
     */
	public function defaultSort($sorts){
		$this->default_sort = $sorts;
		return $this;//for chaining
	}

    /**
     * Enable request to sort query
     *
     * @param array $opt accept:only, except
     * @return void
     */
    public function applySort($opt = [])
    {
        $classOrSorts = $this->model;
        $finalSpecs = $classOrSorts;
        if (!is_array($finalSpecs)) {
            //it is class
            $className = $classOrSorts;
            $classObj = new $className;

            //get from queryables
            $queryableSpecs = [];
            if(method_exists($className, 'getNormalizedQueryables')){
                $queryableSpecs = $className::getNormalizedQueryables(function($type, $realName, $queriability){
                    if(
                        str_contains($queriability, '|sort|') ||
                        str_contains($queriability, '|all|')
                    ){
                        return $realName;
                    }
    
                    return false;
                });
            }
            
            //get other sortable
            $sortableSpecs = $classObj->sortable;

            //merge specifications
            $finalSpecs = collect($queryableSpecs)->merge($sortableSpecs);
        }

        //make specs uniform
        $finalSpecs = collect($finalSpecs)
            ->mapWithKeys(function ($sort, $key) {
                if (is_numeric($key)) {
                    return [$sort=>$sort];
                } else {
                    return [$key=>$sort];
                }
            }
        );

        //filter specs from option
        if (array_has($opt, 'only')) {
            $finalSpecs = $finalSpecs->only($opt['only']);
        } elseif (array_has($opt, 'except')) {
            $finalSpecs = $finalSpecs->except($opt['except']);
        }

        //get requested sort
        $requestSorts = request("sortby");
        if (!$requestSorts) {
			//if no sort requested, sort use default sort
			if($this->default_sort){
				$requestSorts = $this->default_sort;
			}else{
				//no sort
				return $this;
			}
        }
        
        //wrap in array
        if (!is_array($requestSorts)) {
            $requestSorts = [$requestSorts];
        }

        foreach ($requestSorts as $requestSort) {
            $dir="asc";
            
            //decide direction
            if (ends_with($requestSort, "_desc")) {
                $requestSort = substr($requestSort, 0, count($requestSort) - 6);
                $dir = "desc";
            } elseif (ends_with($requestSort, "_asc")) {
                $requestSort = substr($requestSort, 0, count($requestSort) - 5);
            }

            //do short
            if (array_has($finalSpecs, $requestSort)) {
                //sort available
                $sort = array_get($finalSpecs, $requestSort);
                $overrideFunc = 'sortBy'.studly_case($sort);
                
                //check if has override sort function                
                if(isset($classObj) && method_exists($classObj, $overrideFunc)){
                    $classObj->$overrideFunc($this->query, $dir);
                }else{
                    //sort by attribute name
                    $this->query->orderBy($sort, $dir);
                }
            }
        }

        //for chaining
        return $this;
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
        return $this->applyFilter()
            ->select()
            ->applySort();
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
}