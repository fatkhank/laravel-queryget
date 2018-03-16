<?php

namespace Hamba\QueryGet;
use DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class QG{
    use Concerns\HandleSelection;
    use Concerns\HandleFilter;
    use Concerns\HandleSort;

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
        if(!is_string($className)){
            $className = get_class($className);
        }
        if(array_key_exists($className, self::$instanceCache)){
            return self::$instanceCache[$className];
        }
        return (self::$instanceCache[$className] = new $className);
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
    
    /**
     * Join current query with required relations
     *
     * @param string $join relation name relative from current model, delimited by dot
     * @return string alias of join
     */
    public function leftJoin($join){
        $joinInfo = $this->leftJoinInfo($join);
        return $joinInfo? $joinInfo['alias'] : null;
    }

    /**
     * Join current query with required relations
     *
     * @param string $join relation name relative from current model, delimited by dot
     * @return string model name of last relation joined
     */
    public function leftJoinModel($join){
        $joinInfo = $this->leftJoinInfo($join);
        return $joinInfo? $joinInfo['class'] : null;
    }

    /**
     * Join current query with required relations
     *
     * @param string $join relation name relative from current model, delimited by dot
     * @return array information of join result, containing ['alias' => 'alias of the join', 'class' => 'name of model of last join']
     */
    public function leftJoinInfo($join){
        //prevent duplicate join
        if(array_key_exists($join, $this->joined)){
            return $this->joined[$join];
        }

        //save last join alias
        $lastJoin = null;
        $joinAlias = null;
        $sourceModel = $this->model;
        $splittedJoins = explode('.', $join);
        foreach ($splittedJoins as $splittedJoin) {
            $lastJoin = $this->doLeftJoin($sourceModel, $splittedJoin, $joinAlias);
            if(!$lastJoin){
                //fail to join
                return null;
            }
            $joinAlias = $lastJoin['alias'];
            $sourceModel = $lastJoin['class'];
        }

        return $lastJoin;
    }

    private function doLeftJoin($sourceModel, $join, $prefix){
        //prevent duplicate join
        if(array_key_exists($join, $this->joined)){
            return $this->joined[$join];
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

        if($prefix){
            $joinAlias = $prefix.'_'.$relationName.'_join';
        }else{
            $prefix = $classObj->getTable();
            $joinAlias = $relationName.'_join';
        }
        
        //parse relation
        $relatedClass = $relation->getRelated();
        $relatedTbl = $relatedClass->getTable();
        $foreignKey = $relation->getForeignKey();
        $ownedKey = $relation->getOwnerKey();

        if ($relation instanceof BelongsTo) {
            //belongsTo need foreign
            $this->query->leftJoin($relatedTbl.' as '.$joinAlias, $joinAlias.'.'.$ownedKey, '=', $prefix.'.'.$foreignKey);
        }

        return ($this->joined[$join] = [
            'class' => $relatedClass,
            'alias' => $joinAlias,
        ]);
    }

    /**
     * Change array of dot notation string to tree
     *
     * @param [type] $flatDotList
     * @return void
     */
    public static function inflate($flatDotArray, $leaf = null){
        $flatDotArray = array_sort($flatDotArray);
        $tree = [];
        foreach ($flatDotArray as $value) {
            array_set($tree, $value, $leaf);
        }
        return $tree;
    }

    
    public static function normalizeList($arrayOrStringCSV, $delimiter=','){
        if(is_string($arrayOrStringCSV)){
            //if props is concatenated string, parse it
            return explode(',', $arrayOrStringCSV);
        }

        return array_wrap($arrayOrStringCSV);
    }

    /**
     * Include only array value(s) that match array of pattern
     *
     * @param array $array
     * @param array $patterns
     * @return void
     */
    public static function filterOnly($array, $patterns){
        return array_filter($array, function($value) use ($patterns){
            foreach ($patterns as $pattern) {
                if(str_is($pattern, $value)){
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * Exclude array value(s) that match any of pattern specified
     *
     * @param array $array
     * @param array $patterns
     * @return void
     */
    public static function filterExcept($array, $patterns){
        return array_filter($array, function($value) use ($patterns){
            foreach ($patterns as $pattern) {
                if(str_is($pattern, $value)){
                    return false;
                }
            }
            return true;
        });
    }

    public static function onlyKeys($array, $patterns){
        return collect($array)->filter(function($value, $key) use ($patterns){
            foreach ($patterns as $pattern) {
                if(str_is($pattern, $key)){
                    return true;
                }
            }
            return false;
        });
    }

    public static function exceptKeys($array, $patterns){
        return collect($array)->filter(function($value, $key) use ($patterns){
            foreach ($patterns as $pattern) {
                if(str_is($pattern, $key)){
                    return false;
                }
            }
            return true;
        });
    }
}