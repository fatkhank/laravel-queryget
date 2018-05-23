<?php

namespace Hamba\QueryGet;
use DB;

class QG{
    use Concerns\HandleSelection;
    use Concerns\HandleFilter;
    use Concerns\HandleSort;
    use Concerns\HandleJoin;
    use Concerns\HandlePagination;

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
        //total results count
        $total = $this->query->count();

        //paginate param
        $count = $this->pageSize();
        $page = $this->pageNumber();
        $skip = $this->offset($page, $count);
        $this->paginate();

        //query results
        $data = [];

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
                'page' => $page,
                'skip' => $skip,
                'size' => $count
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
     * Proxy to call query->findOrFail()
     *
     * @param mixed $id
     * @return void
     */
    public function iof($id){
        return $this->query->findOrFail($id);
    }

    /**
     * Get one row specific id, then return value of attribute specified
     *
     * @param string $attribute Attribute name
     * @param mixed $id Id of row
     * @param mixed $defaultIfNull Value returned if row null
     * @return void
     */
    public function valueOf($attribute, $id, $defaultIfNull = null){
        $row = $this->select($attribute)->query->find($id);
        if(!$row || !$row->$attribute){
            return $defaultIfNull;
        }
        return $row->$attribute;
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