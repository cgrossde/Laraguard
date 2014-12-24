<?php namespace CGross\Laraguard;

use Exception;
use Illuminate\Http\Request;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Parse permission.yml and return permissions that match the request => getPermissionsForRequest()
 * The implementor then only needs to check if the user has one of the returned permissions.
 */
class PermissionParser {

	/**
	 * @var Instance of symfonys yaml parser
	 */
	private $yamlParser;

	/**
	 * @var Array Collect errors here
	 */
	private $errors;

	/**
	 * @var Array from permissions.yml
	 */
	private $permissionConf = null;

	/**
	 * @var RequestParser
	 */
	private $requestParser;

	/**
	 * @param RequestParser $requestParser
	 */
	public function __construct(RequestParser $requestParser) {
		$this->requestParser = $requestParser;
	}

	/**
	 * Tries to load configuration from permissions.yml
	 *
	 * @return bool
	 */
	public function loadPermissions() {
		$this->yamlParser = new Parser();
		try {
			$this->permissionConf = $this->yamlParser->parse(file_get_contents(base_path().'/resources/config/permissions.yml'));
		} catch (ParseException $e) {
			$this->errors[] = 'Error parsing config/permissions.yml';
			return false;
		} catch (Exception $e) {
			$this->errors[] = 'Error reading config/permissions.yml';
			return false;
		}
		return true;
	}

	public function getPermissionsForRequest(Request $request) {
		// Check model and action matching permissions
		$resultModelAction = $this->checkForMatchingRules();
		// Check custom regex permissions
		$resultCustom = $this->checkForCustomRules($request->route()->getAction()['controller']);
		// Default permissions? model.action, model.all (=> model@*), all.action (=> *@action), all.all (=> *@*)

		// Return result
		return array_unique(array_merge($resultModelAction, $resultCustom));
	}

	private function checkForMatchingRules() {
		$result = array();
		if(! $this->requestParser->getModel() || ! $this->requestParser->getControllerMethod()) {
			$this->errors[] = 'No model or method to match against';
			return null;
		}
		// Iterate over permissions
		foreach($this->permissionConf['modelActionPermissions'] as $permissionName => $permissionRules) {
			// Iterate over permissionRules
			foreach($permissionRules as $condition) {
				// Validate condition and match it
				list($model, $method) = explode('@', $condition);
				if($model && $method) {
					if($this->isRuleAMatchFor($model, $method)) {
						$result[] = $permissionName;
						break;
					}
				} else {
					$this->errors[] = 'Invalid permission condition: '.$condition.' for permission '.$permissionName;
				}
			}
		}
		return $result;
	}

	private function checkForCustomRules($classPathAndMethod) {
		$result = array();
		// Iterate over permissions
		foreach($this->permissionConf['customPermissions'] as $permissionName => $permissionRules) {
			// Iterate over permissionRules
			foreach($permissionRules as $permissionRegex) {
				// Validate regex
				preg_match($permissionRegex, $classPathAndMethod, $matches);
				if(count($matches) > 0) {
					$result[] = $permissionName;
					break;
				}
			}
		}
		return $result;
	}

	private function isRuleAMatchFor($ruleModel, $ruleAction) {
		if(($ruleModel === $this->requestParser->getModel() || $ruleModel === '*') &&
			($ruleAction === $this->requestParser->getControllerMethod() || $ruleAction === '*')) {
			return true;
		}
		return false;
	}

	public function hasErrors() {
		if(count($this->errors) > 0) {
			return true;
		}
		return false;
	}

	public function getErrors() {
		return $this->errors;
	}

	public function hasNoPermissionRoute() {
		if($this->permissionConf !== null && $this->permissionConf['defaultNoPermissionRoute'] !== 'NONE') {
			return true;
		}
		return false;
	}

	public function getNoPermissionRoute() {
		if($this->permissionConf !== null) {
			return $this->permissionConf['defaultNoPermissionRoute'];
		}
		return '/';
	}

}