# Laraguard WIP
*Very early beta and WIP*

Adds a permission system to Laravel 5 using the new integrated authentication mechanism. Instead of protecting routes, it protects the controller and its methods. This way you do not expose protected functionality if you forgot to protect a certain route. Controllers are protected with a simple syntax: `ControllerName@MethodName`. If you have a `ClientController.php` and want to add a permission called *client.edit* you would do something like this:
```
client.edit:
  - Client@edit
  - Client@update
```


Laraguard also supports `*` if you want to allow all methods or all controllers:

```
client.admin:
  - Client@*

site.admin:
  - "*@*"
```

## Installation

**Composer:**

Integrate this into your laravel projects `composer.json` and execute `composer update`:

```
    "require": {
        "cgross/laraguard": "dev-master",
    },

    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/cgrossde/laraguard"
        },
    ],
```

**Add middleware:**

Add this line to the `$routeMiddleware` array in `app/Http/Kernel.php`:
```
'laraguard' => 'CGross\Laraguard\Middleware\Permission',
```

**Protect specific or all controllers:**

For every controller that you want to protect from unauthorized access call the laraguard middleware in the constructor like this:
```
   /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('laraguard');
    }
```

If you want put every controller under the protection of laraguard, add `'CGross\Laraguard\Middleware\Permission'` to the `$middleware` array in `app/Http/Kernel.php` and remove the entry from `$routeMiddleware`. Note that you need to allow guests access to the Auth and Password controllers to login or send a password reset link.

**Extend the User model:**

Extend the `User` model with a method `getPermissions` which returns an array with the users permissions. You might also want to extend the user schema to save permissions in the database. If *Laraguard* is not flexible enough for your needs you can create a new database table for user roles which then references permissions assigned to those roles. Implement it like you want, just make sure the `getPermissions` method exists in the `User` model and that it returns an array with permission names.


## Permissions

Create an new file `resources/config/permission.yml` with the following content, adapted to your needs:

```
defaultNoPermissionRoute: '/denied'

modelActionPermissions:
  admin:
    - "*@*"
  guest:
    - "Welcome@index"
    - "Auth@*"
    - "Password@*"
  user:
    - "Client@index"
    - "Client@show"
  manager:
    - "Client@*"

customPermissions:
  customPermission1:
    - /SomeRegexThatMatchesTheControlerPathAndMethod/
  someOtherCustomPermission:
    - /AnotherRegex/

```

Note: The default permission for users that are not logged in is `guest`.