# Laravel Query Get

Laravel query get is helper to perform query to eloquent model.

## Installation
```
composer require hamba/queryget
```

## Usage
You have these models:
```php
class User extends Model{
    protected $fillable = [
        'username', 'email'
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_users');
    }

    public $queryable = [
        'email' => 'string',//enable attribute email to be queried
        'name' => 'string:username',//enable attribute with alias
        'roles' => 'relation'//enable relation 'roles'
    ];
}
```
```php
class Role extends Model
{
    protected $fillable = [
        'name', 'permissions',
    ];
    public $queryable = ['name', 'permissions'];
}
```

and you have this controller:
```php
class UserController extends Controller
{
    public function index()
    {
        return qg(User::class)->apply()->get();
    }
}
```

and this route:
```php
Route::get('users', 'UserController@index');
```

## With QueryGet you can do these requests:
### Select attribute or relation:
* /users?props[]=name
```json
{
    "total":3,
    "data":[
        {"id":1,"name":"User 1"},
        {"id":2,"name":"User 2"},
        {"id":3,"name":"User 3"},
    ]
}
```

* /users?props[]=name&props[]=roles.name
```json
{
    "total":3,
    "data":[
        {
            "id":1,
            "name":"User 1",
            "email":"user1@mail.com",
            "roles":[
                {"id":1,"name":"admin"},
                {"id":2,"name":"manager"}
            ]
        },
        {
            "id":2,
            "name":"User 2",
            "email":"user2@mail.com",
            "roles":[
                {"id":2,"name":"manager"}
            ]
        }, 
        {
            "id":3,
            "name":"User 3",
            "email":"user3@mail.com",
            "roles":[
                {"id":3,"name":"employee"}
            ]
        }, 
    ]
}
```
### Filter attribute or relation:
* /users?props=id&email=user1%
```json
{
    "total":1,
    "data":[{"id":1}]
}
```

### Sort attribute or relation
* /users?props[]=id&props[]=name&sortby=name_desc
```json
{
    "total":3,
    "data":[
        {"id":3,"name":"User 3"},
        {"id":2,"name":"User 2"},
        {"id":1,"name":"User 1"}
    ]
}
```

### Do pagination
* /users?props[]=name&page=1&pagesize=1
```json
{
    "total":3,
    "data":[
        {"id":2,"name":"User 2"},
    ]
}
```


## [Read full documentation](docs/doc.md)