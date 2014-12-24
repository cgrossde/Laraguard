<?php namespace CGross\Laraguard\Middleware;

use Closure;
use Exception;
use CGross\Laraguard\PermissionParser;
use CGross\Laraguard\RequestParser;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Routing\Middleware;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;

class Permission implements Middleware {

	/**
	 * The Guard implementation.
	 *
	 * @var Guard
	 */
	protected $auth;

	/**
	 * @var \Illuminate\Http\Request  $request
	 */
	protected $request;

	/**
	 * @var \Closure
	 */
	protected $next;

	/**
	 * @var PermissionParser
	 */
	protected $permissionParser;

	/**
	 * Convenient access to model, controller, method, ...
	 * @var RequestParser
	 */
	protected $requestParser;

	/**
	 * Create a new filter instance.
	 *
	 * @param  Guard  $auth
	 * @return void
	 */
	public function __construct(Guard $auth)
	{
		$this->auth = $auth;
	}

	/**
	 * Needed to store Closure as property and make it callable
	 * http://stackoverflow.com/questions/4535330/calling-closure-assigned-to-object-property-directly
	 */
	public function __call($method, $args)
    {
        if(is_callable(array($this, $method))) {
            return call_user_func_array($this->$method, $args);
        }
    }


	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		$this->next = $next;
		$this->request = $request;
		$this->requestParser = new RequestParser($request);
		$this->permissionParser = new PermissionParser($this->requestParser);
		// Try to load permissions
		if(! $this->permissionParser->loadPermissions()) {
			return $this->returnError($this->permissionParser->getErrors());
		}
		// Always allow defaultNoPermissionRoute and permissionDenied method
		if($this->isRequestToDefaultRouteOrPermissionDeniedMethod()) {
			return $next($request);
		}
		// Get permissions that allow this request
		$allowedPermissions = $this->permissionParser->getPermissionsForRequest($request);

		// Check if user has one of those permissions
		if($this->checkUserPermissionsAgainstAllowedPermissions($allowedPermissions)) {
			return $this->allowRequest();
		}
		return $this->denyRequest();

	}

	/**
	 * Check if this request goes to our defaultPermissionDenied route
	 * or a permissionDenied method. Those need to be allowed, always!
	 *
	 * @return boolean
	 */
	private function isRequestToDefaultRouteOrPermissionDeniedMethod() {
		// Check if request goes to method permissionDenied
		// We need to always allow that or we might end in an infinite loop
		if($this->requestParser->getControllerMethod() === 'permissionDenied') {
			return true;
		}
		// Check if request goes to defaultNoPermissionRoute
		// We need to always allow that or we might end in an infinite loop
		else if($this->permissionParser->hasNoPermissionRoute()
				&& $this->permissionParser->getNoPermissionRoute() === $this->request->path()) {
			return true;
		}
		else {
			return false;
		}
	}

	private function allowRequest() {
		return $this->next($this->request);
	}

	/**
	 * Return error, use JSON if requested
	 *
	 * @param  array $errors  Array with errors
	 */
	private function returnError($errors) {
		if($this->request->wantsJson()) {
			return response(json_encode($errors), 501);
		} else {
			return response(implode('.', $errors), 501);
		}
	}

	// Relfect controller and check for method permissionDenied method
	// If non existent, use view defined in permissions.yml (noMatchView)
	// If NONE, throw 503 error
	private function denyRequest() {
		// Redirect to permissionDenied method of controller
		if($this->requestParser->hasControllerPermissionDeniedMethod()) {
			// Modify request action to direct to method 'permissionDenied'
			$action = $this->request->route()->getAction();
			$controllerPath = $this->requestParser->getControllerPath();
			$action['uses'] = $controllerPath . '@permissionDenied';
			$action['controller'] = $controllerPath . '@permissionDenied';
			// Set new action
			$this->request->route()->setAction($action);
			return \Route::dispatch($this->request);
		}
		// Redirect to default defaultNoPermissionRoute
		else if($this->permissionParser->hasNoPermissionRoute()) {
			return redirect($this->permissionParser->getNoPermissionRoute());
		}
		// Return permission denied error
		else {
			return $this->returnError(['Permission denied']);
		}
	}

	private function checkUserPermissionsAgainstAllowedPermissions($allowedPermissions) {
		$user = $this->auth->user();
		if($user === null) {
			$userPermissions = ['guest'];
		} else {
			$userPermissions = $user->getPermissions();
		}

		// Intersect permissions
		$validPermissions = array_intersect($userPermissions, $allowedPermissions);

		if(count($validPermissions) > 0) {
			return true;
		}
		return false;
	}
}
