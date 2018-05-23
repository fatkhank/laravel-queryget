# Introduction
This doc use these eloquent models as sample. You can apply QG to any eloquent model you want.

```php
class Person{
    //required trait
    use \Hamba\QueryGet\Queryable;

    protected $table = 'people';

    protected $fillable = [
        'id',
        'name',
        'is_active',
        'parent_id',
        'born_at'
    ];

    public function parent(){
        return $this->belongsTo(Person::class, 'parent_id');
    }

    public function children(){
        return $this->hasMany(Person::class, 'parent_id');
    }
}
```

With this data:
```php
$p1 = new Person([
    'id' => 1,
    'name' => 'Ahmad',
    'is_active' => true
]);
$p2 = new Person([
    'id' => 2,
    'name' => 'Yusuf',
    'is_active' => true,
    'parent_id' => 1
]);
$p3 = new Person([
    'id' => 3,
    'name' => 'Lisa',
    'is_active' => true,
    'parent_id' => 2
]);
```

# Basic Usage
## Creating Instance
First, create instance of \Hamba\QueryGet\QG:
```php
$qg = qg(Person::class);//create using helper function
$qg = new QG(Person::class);//create using constructor
$qg = Person::qg();//create from model
```
Beside using model, you can also passing query created from eloquent model as parameter:
```php
$query = Person::where('is_active', true);
$qg = qg($query);
$qg = new QG($query);
```

## Getting original query
You can get the Illuminate\Database\EloquentBuilder of QG object by calling `$qg->query`.

## Fetching result
You can fetch result using one of these:
```php
//fetch one
$result = $qg->query->first();
//or
$result = $qg->one();

//fetch one or fail if not exists
$result = $qg->query->firstOrFail();
//or
$result = $qg->fof();

//find using id
$result = $qg->query->find($id);
//or
$result = $qg->id($id);

//find using id or fail if not exists
$result = $qg->query->findOrFail($id);
//or
$result = $qg->iof($id);

//fetch many
$result = $qg->query->get();

//fetch, then wrap as json
$result = $qg->get();

//get only attribute value
$result = $qg->select('name')->id($id)->name;
//or
$result = $qg->valueOf('name', $id);
```

# Selection
Add `\Hamba\QueryGet\Selectable` trait and `$selectable` attribute to model. Then define all attribute or relation that can be selected.
```php
//dont forget the trait
use \Hamba\QueryGet\Queryable;

/**
 * Attributes or relations that can be selected
 *
 * @var array
 */
public $selectable = [
    //'key_alias' => 'key',
    'id',
    'name',
    'is_active',
    'parent',
];
```

## Perform selection
```php
// format: $qg->select($selections, $opt);

//selecting an atribute
$qg->select('name')->one();
// result:
// [
//      'name' => 'Ahmad',
// ]

//selecting multiple attributes
$qg->select(['name', 'id'])->one();
//or
$qg->select('name,id')->one();
// result:
// [
//      'id' => 1,
//      'name' => 'Ahmad',
// ]

//selecting all attribute
$qg->select('*')->id(2);
//or
$qg->select(['*'])->id(2);
// result:
// [
//      'id' => 2,
//      'name' => 'Yusuf',
//      'is_active' => true,
// ]
```
You can also select relations. Relation will be fetched as nested object, same as using elequent `$query->with()`.
```php
//selecting relation
$qg->select('parent.name')->id(2);
// result:
// [
//      'parent_id' => 1,
//      'parent' => [
//          'id' => 1,
//          'name' => 'Ahmad'
//      ]
// ]

//select deep relation and relation's attributes
$qg->select('name,parent.parent.*')->id(3);
// result:
// [
//      'name' => 'Lisa',
//      'parent_id' => 2,
//      'parent' => [
//          'id' => 2,
//          'parent_id' => 1,
//          'parent' => [
//              'id' => 2,
//              'name' => 'Ahmad',
//              'is_active' => true,
//          ]
//      ]
// ]
```
> When selecting relations, owned and foreign keys involved will be automatically included in selection. They must be registered in `$selectable`. If you forget to register them, the relation may not be fetched.

You can call `select()` multiple times, and all selections will be applied. To undo selection use `unselect($patterns)`.
```php
$qg->select('name')->select('id')->one();
// result:
// [
//      'id' => 1,
//      'name' => 'Ahmad',
// ]

// Without unselect():
$sample = $qg
    ->select('name')
    ->select('parent.parent.name')
    ->select('parent.parent.is_active')
    ->select('parent.name');
$sample->id(3);
// result:
// [
//      'name' => 'Lisa',
//      'parent_id' => 2,
//      'parent' => [
//          'id' => 2,
//          'name' => 'Yusuf',
//          'parent_id' => 1,
//          'parent' => [
//              'id' => 2,
//              'name' => 'Ahmad',
//              'is_active' => true,
//          ]
//      ]
// ]

// With unselect():
$sample->unselect('*name')->id(3);
// result:
// [
//      'parent_id' => 2,
//      'parent' => [
//          'id' => 2,
//          'parent_id' => 1,
//          'parent' => [
//              'id' => 2,
//              'is_active' => true,
//          ]
//      ]
// ]
```

## Aliasing Selectable
In `$selectable`, you can specify alias for attribute:
```php
// IN MODEL
$selectable = [
    //key_alias => key
    'fullname' => 'name',
]

// IN CONTROLLER
$qg->select('fullname')->id(2);
// result:
// [
//     'fullname' => 'Yusuf',
// ]
```
If you want to alias relationship, use proxy function that call original relation.
```php
// IN MODEL
$selectable = [
    'father',
]

public function father(){
    returh $this->parent();
}

// IN CONTROLLER
$qg->select('father.id')->id(2);
// result:
// [
//     'id' => 1,
// ]
```

## Selection from request
If `$selections` is not specified or using `null` value, it will use request input `props` as `$selections`.
```php
$qg->select()->id(2);
//or
$qg->select(null)->id(2);
// requesting to `{URL}?props=name,id`
// result:
// [
//     'id' => 2,
//     'name' => 'Yusuf'
// ]
```

## Whitelist selection
```php
$qg->select(null, ['only'=>'name,id'])->id(3);
$qg->selectOnly('name,id')->id(3);
// requesting to `{URL}?props=name,is_active`
// result:
// [
//     'name' => 'Lisa'
// ]
```

## Blacklist selection
```php
$qg->select(null, ['except'=>'name,id'])->id(3);
$qg->selectExcept('name,id')->id(3);
// requesting to `{URL}?props=name,is_active`
// result:
// [
//     'is_active' => true
// ]
```

## Custom Selection
You can make custom select function by add to model function with name `'select' + studly_case($key)`
```php
//IN MODEL
$selectable = [
    'big_name',
];

public function selectBigName($tablePrefix, $aliasedKey){
    return \DB::raw("UPPER(".$tablePrefix.".name) as ".$aliasedKey);
}

//IN CONTROLLER
$qg->select('big_name')->one(2);
// result:
[
    'big_name' => 'YUSUF'
]
```
> When selecting column, remember to prepend with `$tablePrefix` to prevent column name conflict

# Filtering
Add `\Hamba\QueryGet\Filterable` trait and `$filterable` attribute to model. Then define all attribute or relation that can be filtered.

```php
//dont forget the trait
use \Hamba\QueryGet\Queryable;

/**
 * Attributes or relations that can be filtered
 *
 * @var array
 */
public $filterable = [
    //{key_alias} => {type}:{key}
    'id',
    'name' => 'string'
    'ordered_id' => 'number:id',//with alias
    'born_after' => 'date_after:born_at',
    'born_before' => 'date_before:born_at',
];
```

## Filtering attribute
### Filter Single Attribute
```php
// format: $qg->filter($filterKey, $filterValue);
// filter whose name starts with 'Yus'
$qg->filter('name', 'Yus%')->select('id,name')->one();
// result:
// [
//      'id' => 2,
//      'name'=> 'Yusuf'
// ]
```
### Filter Multiple Attributes
```php
// format: $qg->filter($filterKeyValuePairs, $opt);
// filter whose name is lisa and ordered_id greater than 2
$qg->filter([
    'name'=>'Lisa',
    'ordered_id' => 'gt:2'
])->select('id,name')->one();
// result:
// [
//     'id' => 3,
//     'name' => 'Lisa'
// ]
```

## Filtering relation
### Filter Relation Existence
```php
// 1.filter person with grandparent
$qg->filter('parent$parent', true)->select('id,name')->one();
// result:
// [
//     'id' => 3,
//     'name' => 'Lisa'
// ]

// 2.filter person with no parent
$qg->filter('parent', false)->select('id,name')->one();
// result:
// [
//     'id' => 1,
//     'name' => 'Ahmad'
// ]
```

### Filter Relation Attribute
```php
// filter whose parent name starts with 'Ah'
$qg->filter('parent$name', 'Ah%')->select('id,name')->one();
// result:
// [
//     'id' => 3,
//     'name' => 'Lisa'
// ]

```

## Disjunction
You can using disjunction by concatenating filter keys with `'_or_'`. Sample:
```php
// Filter person whose name or parent's name or grandparent's name is Ahmad.
$qg->filter('name_or_parent$name_or_parent$parent$name', 'Ahmad')
```

## Filter from request
If `$filterKeyValuePair` is not specified or using `null` value, it will use all request input instead. Inputs that have null value will be ignored. For filtering nullability, see below.
```php
$qg->filter()->select('name')->one();
//or
$qg->filter(null)->select('name')->one();
// requesting to `{URL}?name=Yus%&id=
// result:
// [
//     'name'=>'Yusuf'
// ]
```
You can also specify filters to be applied, if not provided in request inputs by specify 'default' option in `filter()` or using `filterWithDefault()`.
```php
$qg->filterWithDefault(['name', 'Yus%'])->select('name')->one();
//or
$qg->filter(null, ['default'=>['name', 'Yus%']])->select('name')->one();
// requesting to `{URL}
// result:
// [
//     'name'=>'Yusuf'
// ]
// requesting to `{URL}?name=Lis%&id=
// result:
// [
//     'name'=>'Lisa'
// ]
```

## Built in filter types
Each types has rules on how the value will be treated. Some types have magic value. These are magic value common to all built in types.
Filter Value                        | Description
---                                 | ---
`'null:'`                           | Filter where value is null
`'notnull:'`                        | Filter where value is not null
### 1. bool/boolean
Filter Value                        | Description
---                                 | ---
`false` or `'false'` or `'no'`      | Filter where key has false value
`true` or `'true'` or `'yes'` or `1`| Filter where key has true value

### 2. string
All values are case insensitive.
Filter Value     | Description
-----| -----------
`'empty:'`      | Filter where value is empty
`'not:{VALUE}'`        | Filter where value is not like `{VALUE}`
`'like:{VALUE}'` or `{VALUE}`     | Filter where value like `{VALUE}`. Use `'like:{VALUE}'` to escape other magic value.

### 3. number
Filter Value    | Description           | Sample
---             | ---                   | ---
`'gt:'`         | Filter where value is greater than filterValue    |?ordered_id=gt:3
`'gte:'`        | Filter where value is greater than or equals filterValue    |?ordered_id=gte:3
`'lt:'`         | Filter where value is less than filterValue    |?ordered_id=lt:3
`'lte:'`        | Filter where value is less than or equals filterValue    |?ordered_id=lte:3

### 4. date
Filter Value    | Description
---             | ---
`{VALUE}`         | Filter where value equals with `{VALUE}`

### 5. date_min
Filter Value    | Description
---             | ---
`{VALUE}`       | Filter where value is >= `{VALUE}`

### 6. date_max
Filter Value    | Description
---             | ---
`{VALUE}`       | Filter where value is <= `{VALUE}`

### 5. date_after
Filter Value    | Description
---             | ---
`{VALUE}`       | Filter where value is > `{VALUE}`

### 6. date_before
Filter Value    | Description
---             | ---
`{VALUE}`       | Filter where value is < `{VALUE}`

## Custom filter type
You can define new type by providing function with name `'createFilter' + studly_case($customTypeName)` in model using it in `$filterable` attribute.
```php
public $filterable = [
    'name_length' => 'length:name',//name is $key
]

/**
 * Create filter length
 *
 * @param string $key attribute name passed as parameter of length type
 */
public function createFilterLength($key, $table){
    $qualifiedColumnName = $table.'.'.$key;
    return function($query, $value) use ($qualifiedColumnName){
        return $query->where(\DB::raw('CHAR_LENGTH('.$qualifiedColumnName.')'), '<', $value);
    };
}
```
> Dont forget to prepend column with table name to prevent naming conflict!

## Custom filter function
Beside using custom filter type, you can make custom filter function. The difference is that custom filter type can be used many times while custom filter function can be used once. Add function with name`'filter'+studly_case($filterKey)` in the model.
```php
public $filterable = [
    'is_anonym', //custom filter (filter function specified later)
];

/**
 * Filter anonym
 *
 * @param mixed $query
 * @param boolean value
 */
public function filterIsAnonym($query, $value){
    if($value === true){
        $query->whereNull('name');
    }else if($value === false){
        $query->whereNotNull('name');
    }
}
```
Sample usage of custom filter above:
```php
$qg->filter('is_anonym', false)
```

# Sorting
Add $sortable attribute to model. Then define all attribute or relation that can be sorted.
```php
//dont forget the trait
use \Hamba\QueryGet\Queryable;

/**
 * Attributes or relations that can be sorted
 *
 * @var array
 */
public $sortable = [
    'name',
];
```

## Perform sorting
Perform sort by supplying attribute name append with direction ('_asc' or '_desc').
```php
// format: $qg->sort($sortsToApply, $opt);
$qg->sort('name_asc');//sort name ascending

//sorting by relation's attribute
$qg->sort('parent.parent.name_desc,');//sort by grandparent's name descending

//multiple sorting
$qg->sort('parent.name_asc,name_desc');//sort by parent's name, then sort by their name
```
If direction not specified, ascending will be used
```php
$qg->sort('name_asc');
//same as
$qg->sort('name');
```

## Sorting from request
If `$sortsToApply` is not specified or using `null` value, it will use request input `sorts` or `sortBy` as `$sortsToApply`. Both input accepting array of sort string or sort strings concatenated by comma.
```php
$qg->sort();
//or
$qg->sort(null);

// requesting to `{URL}?sorts=name_desc,id`
// or
// requesting to `{URL}?sortBy[]=name_desc&sortBy[]=id`
```

## Default sort
If no sort input in request, default sort will be used. If default is not set, no sort will be applied. You can set default sort: `$qg->defaultSort('name_asc');`

## Whitelist sort
```php
$qg->sort(null, ['only'=>'name,id'])->id(3);
$qg->sortOnly('parent.name')->id(3);
// requesting to `{URL}?sorts=name_asc` has no effect
```

## Blacklist sort
```php
$qg->sort(null, ['except'=>'name,id'])->id(3);
$qg->sortExcept('name,id')->id(3);
// requesting to `{URL}?sorts=name_asc` has no effect
```

## Custom sort
You can make custom select function by add to model function with name `'sortBy' + studly_case($sortKey)`
```php
$sortable = [
    'name_length'
];

public function sortByNameLength($query, $dir){
    $query->orderBy(\DB::raw('CHAR_LENGTH(name)'), $dir);
}
```

# Applying select, filter, and sort
You can merge configuration in `$selectable`, `$filterable` and `$sortable` into one by adding `$queryable` to your model and register attributes or relations that can be filtered, selected and sorted. If you want to disable some of features, consider moving them to `$selectable`, `$filterable` or `$sortable`.
```php
public $queryable = [
    'id',
    'name',
    'parent'
];
```
To apply selection, filter and sort to query, you can use one of these:
```php
$qg->filter()->select()->sort();
// or shorthand
$qg->apply();
```

# Wrapper
When calling `$qg->get()` the result is wrapped using wrapper. Default wrapper wrap result in json with following format:
```json
{
    'total': 0,//total of rows (without pagination)
    'data': []//paginated result
};
```

## No wrapper
`$qg->get(false)` will not wrap result, instead returning only list of paginated data as array.

## Custom wrapper
You can make custom wrapper by calling `QG::addWrapper($wrapperName, $wrapperFunc)` in your AppServiceProvider. The `$wrapperFunc` is function with signature `function($data, $meta, $wrapperParam)`. Data is array of data from `$qg->query->get()` after being paginated. `$meta` is array with following content:
Key         | Value
---         | ---
`'total'`   | total result without paging
`'skip'`    | sql offset
`'size'`    | rows count per page
`'page'`    | current pagenumber
Sample:
```php
QG::addWrapper('keyby', function($data, $meta, $wrapperParam){
    $mapped = collect($data)->keyBy($wrapperParam);
    return [
        'total_rows' => $meta['total'],
        'result' => $data
    ];
});
```
To use custom wrapper, pass wrapperName and wrapperParameter in `get()`:
```php
$qg->select('id','name')->get('keyby', 'id');
// result:
// [
//     1 => [
//         'id' => 1,
//         'name' => 'Ahmad'
//     ],
//     2 => [
//         'id' => 2,
//         'name' => 'Yusuf'
//     ],
//     3 => [
//         'id' => 3,
//         'name' => 'Lisa'
//     ]
// ]
```

## Pagination
When using `get()`, pagination is automatically applied. Pagination parameters determined from request inputs:
Input                       | Description
---                         | ---
`'pagesize'` or `'size'` or `'count'`   | Count of rows per page
`'pagenumber'` or `'page'`              | Page number


# Join
## Join defined relation
If you want to join person's parent using traditional join, it would be like this:
```php
$qg->query->leftJoin('people as parent', 'parent.id', '=', 'people.parent_id')
```
If you have parent() relation defined, you can use join shortcut, by calling `leftJoin($joinKey)`
```php
$join = $qg->leftJoin('parent');
```
`$joinKey` key is the name of the relation you want to join that is defined in model.
`$join` will contain alias of the join according to relation name suffixed with '_join' . In the sample, `$join` will be string with value 'parent_join'.

Join also can be nested, like so:
```php
$grandParentJoin = $qg->leftJoin('parent.parent');
```
It will perform following query:
```php
$qg->query
    ->leftJoin('people as parent_join', 'parent_join.id', '=', 'people.parent_id')
    ->leftJoin('people as parent_join_parent_join', 'parent_join_parent_join.id', '=', 'parent_join.parent_id')
```
Notice that final join alias is concatenation of relations involved in join chain.

> If `$qg->leftJoin` called more than once, only first leftJoin will perform real join operation on query. Following left join will only return alias from first operation.

## Custom join
If the relation you want to join is not defined, or you want complex join, you can use custom join. Define function with name `'join'+studly_case($joinKey)` in the model with signature `function ($query, $prefix, $alias)`. `$query` is query join operation will be applied to. `$prefix` is name of relation in query which join operation will be applied to. `$alias` is name of final alias of the custom join.
### Sample
```php
//in the Person model

public function joinChildrenCount($query, $prefix, $alias){
    $childrenSql = Person::groupBy('parent_id')
        ->select('parent_id', \DB::raw('COUNT(id) as children_count_val'))
        ->toSql();

    $query->leftJoin(\DB::raw('('.$childrenSql.') as '. $alias), $alias.'.order_id', '=', $prefix.'.id');
}
```
Using it would be:
```php
$qg->leftJoin('children_count');
```

## Select using join
```php
//in model
public $selectable = [
    'parent_name',
    'children_count',
];

public function selectParentName($tablePrefix, $aliasedKey, $qg){
    $parentJoin = $qg->leftJoin('parent');//join shortcut to parent
    return $parentJoin.".name as ".$aliasedKey);
}

public function selectChildrenCount($tablePrefix, $aliasedKey){
    $parentJoin = $qg->leftJoin('parent');//join shortcut to parent
    return $parentJoin.".children_count_val as ".$aliasedKey);
}
```
## Filter using join
```php

public $filterable = [
    'parent_name',
];

public function filterParentName($query, $value, $qg){
    $parentJoin = $qg->leftJoin('parent');
    $filter = \Hamba\QueryGet\Filters\StringFilter::createFilterString('name', $parentJoin);
    $filter($query, $value);
}
```