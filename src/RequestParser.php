<?php namespace CGross\Laraguard;


class RequestParser {

	/**
	 * @var \Illuminate\Http\Request
	 */
	private $request;

	/**
	 * The name of the controller
	 * @var string
	 */
	private $controller;

	/**
	 * The method of the controller that is going to be called by this request
	 * @var string
	 */
	private $controllerMethod;

	/**
	 * Namespaced path to controller class
	 * @var string
	 */
	private $controllerPath;

	/**
	 * Controller path includeing @methodname
	 * @var string
	 */
	private $controllerMethodPath;


	/**
	 * @param \Illuminate\Http\Request  $request
	 */
	public function __construct($request) {
		$this->request = $request;
		$this->controllerMethodPath = $request->route()->getAction()['uses'];
		$this->analyseRequestAndSetVars();
	}

	/**
	 * Analyse request and set vars
	 */
	private function analyseRequestAndSetVars() {
		$this->extractControllerName();
		$this->extractControllerPathAndMethod();
	}

	/**
	 * Determine model from controlerMethodPath
	 */
	private function extractControllerName() {
		// Regex to get intended model and action from request
		$modelActionRegex = '/^.+\\\\Http\\\\Controllers\\\\(?<controller>.+)Controller@.+$/';
		preg_match($modelActionRegex, $this->controllerMethodPath, $result);
		$this->controller = $result['controller'];
	}

	/**
	 * Determine controllerPath and intended controller method
	 */
	private function extractControllerPathAndMethod() {
		list($this->controllerPath, $this->controllerMethod) = explode('@', $this->controllerMethodPath);
	}

	/**
	 * Check controller for permission denied method
	 *
	 * @return bool
	 */
	public function hasControllerPermissionDeniedMethod() {
		try {
			$controllerClass = new \ReflectionClass($this->controllerPath);
			return $controllerClass->hasMethod('permissionDenied');
		} catch(Exception $e) {
			return false;
		}
	}

	/**
	 * @return string
	 */
	public function getControllerName() {
		return $this->controller;
	}

	/**
	 * @return string
	 */
	public function getControllerMethod() {
		return $this->controllerMethod;
	}

	/**
	 * @return string
	 */
	public function getControllerPath() {
		return $this->controllerPath;
	}

	/**
	 * @return string
	 */
	public function getControllerMethodPath() {
		return $this->controllerMethodPath;
	}
}