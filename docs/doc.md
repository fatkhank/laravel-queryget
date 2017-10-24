# Intro
This doc use eloquent model `User` as sample model. You can apply QG to any eloquent model you want.

## Enable model to be selected
Add `$selectable` attribute to model. Then define all attribute or relation that can be selected from request.
```php
//dont forget the trait
use \Hamba\QueryGet\Selectable;

/**
 * Attributes or relations that can be selected
 *
 * @var array
 */
public $selectable = [
    'email',//select attribute
    'name' => 'username',//select attribute with alias
    'roles'//select relation
];
```
## Enable model to be filtered
Add this to eloquent model:
```php
//dont forget the trait
use \Hamba\QueryGet\Filterable;

/**
 * Attributes or relations that can be filtered
 *
 * @var array
 */
public $filterable = [
    'email',//filterr attribute
    'roles'//filter relation
];
```

## Enable model to be sorted
Add this to eloquent model:
```php
//dont forget the trait
use \Hamba\QueryGet\Sortable;

/**
 * Attributes or relations that can be sorted
 *
 * @var array
 */
public $sortable = [
    'id','name', 'email'
];
```

# In the controller
First, create instance of \Hamba\QueryGet\QG using `qg()` helper function.
```php
//create QG from model
$qg = qg(User::class);
```

You can get the Illuminate\Database\EloquentBuilder of QG object by calling `$qg->query`.

## Get the result
`$qg->get()` will execute `get()` function from QG query builder, then wrap the results according to requested format.
```php
//wrap result with default wrapper
$result = $qg->get();
//will return following
$result = [
    'total' => 0,//total of rows (without)
    'data' => []
];

//no wraping will result without wrapped (same as calling $qg->query->get())
$result = $qg->get(false);
$result = [
    [],//row 1
    []//row 2,
];

//
$qg->get('');
```
Write this in the controller.
```php
return qg(User::class)->apply()->get();
```