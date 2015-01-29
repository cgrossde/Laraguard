<?php namespace CGross\Laraguard\Middleware;

use Closure;
use Exception;
use CGross\Laraguard\PermissionParser;
use CGross\Laraguard\RequestParser;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Logging\Log;
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
            $errors = $this->permissionParser->getErrors();
            \Log::error('[Laraguard] ERROR - loading of permissions failed: '.join(' # ', $errors));
			return $this->returnError($errors);
		}
        if($this->permissionParser->debugging()) \Log::info('[Laraguard] REQUEST - ControllerPath: '.$this->requestParser->getControllerMethodPath());
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
            if($this->permissionParser->debugging()) \Log::info('[Laraguard] DENY - with permissionDenied(): '.$action['uses']);
			return \Route::dispatch($this->request);
		}
		// Redirect to default defaultNoPermissionRoute
		else if($this->permissionParser->hasNoPermissionRoute()) {
            $noPermissionRoute = $this->permissionParser->getNoPermissionRoute();
            if($this->permissionParser->debugging()) \Log::info('[Laraguard] DENY - with defaultNoPermissionRoute: '.$noPermissionRoute);
			return redirect($noPermissionRoute);
		}
		// Return permission denied error
		else {
            if($this->permissionParser->debugging()) \Log::info('[Laraguard] DENY - with 501 Error');
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

        // Check if app is in testing mode
        if($this->permissionParser->isAppInTestingMode()) {
            // Merge testing permissions with userPermissions
            $testPerm = array_filter($this->permissionParser->getTestingModePermissions());
            if($this->permissionParser->debugging()) \Log::info('[Laraguard] TESTING permissions: '.join(',',$testPerm));
            $userPermissions = array_merge($userPermissions, $testPerm);
        }
        // Remove null values
        $userPermissions = array_filter($userPermissions);
        $allowedPermissions = array_filter($allowedPermissions);
		// Intersect permissions
		$validPermissions = array_intersect($userPermissions, $allowedPermissions);

		if(count($validPermissions) > 0) {
            if($this->permissionParser->debugging()) \Log::info('[Laraguard] ALLOW - Allowed permissions: '.join(',',$allowedPermissions).' - User: '.join(',',$userPermissions));
			return true;
		}
        if($this->permissionParser->debugging()) \Log::info('[Laraguard] DENY - Allowed permissions: '.join(',',$allowedPermissions).' - User: '.join(',',$userPermissions));
		return false;
	}
}
