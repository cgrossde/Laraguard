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

Laraguard is available on Packagist. Integrate this into your laravel projects `composer.json` and execute `composer update`:

```
    "require": {
        "cgross/laraguard": "0.1.0",
    },
```

**Add middleware:**

Add this line to the `$routeMiddleware` array in `app/Http/Kernel.php`:
```
'laraguard' => 'CGross\Laraguard\Middleware\Permission',
```

**Protect controllers:**

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


**Extend the User model:**

Extend the `User` model with a method `getPermissions` which returns an array with the users permissions. You might also want to extend the user schema to save permissions in the database. If *Laraguard* is not flexible enough for your needs you can create a new database table for user roles which then references permissions assigned to those roles. Implement it like you want, just make sure the `getPermissions` method exists in the `User` model and that it returns an array with permission names.


## Permissions

Create an new file `resources/config/permissions.yml` with the following content, adapted to your needs:

```
defaultNoPermissionRoute: '/denied'

modelActionPermissions:
  admin:
    - "*@*"
  guest:
    - "Welcome@index"
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

**What happens when the user or guest has no permission?**

If the user has no permission for the desired controller method then there are three possibilities:

1. The controller has a method named `permissionDenied`. In this case the method is called. This gives you the ability to display custom permission denied views for different controllers or redirect to some other page
2. The value `defaultNoPermissionRoute` in `permissions.yml` is not `NONE`. In this case the request is redirected to this route
3. Neither a `permissionDenied` nor a `defaultNoPermissionRoute` is set: In this case the response will be a `501 Permission Denied` error page.
