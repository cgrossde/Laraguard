<?php namespace CGross\Laraguard;

use Exception;
use Illuminate\Http\Request;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Parse permission.yml and return permissions that match the request => getPermissionsForRequest()
 * The implementor then only needs to check if the user has one of the returned permissions.
 */
class PermissionParser {

    private $permissionPath;

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
    private $permissionConfOriginal = null;

	/**
	 * @var RequestParser
	 */
	private $requestParser;

	/**
	 * @param RequestParser $requestParser
	 */
	public function __construct(RequestParser $requestParser = null) {
        $this->permissionPath = base_path().'/resources/config/permissions.yml';
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
			$this->permissionConfOriginal = $this->yamlParser->parse(file_get_contents($this->permissionPath));
            $this->permissionConf = $this->permissionConfOriginal;
		} catch (ParseException $e) {
			$this->errors[] = 'Error parsing config/permissions.yml';
			return false;
		} catch (Exception $e) {
			$this->errors[] = 'Error reading config/permissions.yml';
			return false;
		}
		return true;
	}

    /**
     * Determine wich permissions allow this request to go through
     *
     * @param Request $request
     * @return array
     */
    public function getPermissionsForRequest(Request $request) {
		// Check model and action matching permissions
		$resultModelAction = $this->checkForMatchingRules();
		// Check custom regex permissions
		$resultCustom = $this->checkForCustomRules($request->route()->getAction()['controller']);

		// Return result
		return array_unique(array_merge($resultModelAction, $resultCustom));
	}


    /**
     * Iterates over controllerAction rules and compares them
     * with the current controller and action
     *
     * @return array|null
     */
    private function checkForMatchingRules() {
		$result = array();
		if(! $this->requestParser->getControllerName() || ! $this->requestParser->getControllerMethod()) {
			$this->errors[] = 'No controller or method to match against';
			return null;
		}
		// Iterate over permissions
		foreach($this->permissionConf['controllerActionPermissions'] as $permissionName => $permissionRules) {
			// Iterate over permissionRules
			foreach($permissionRules as $condition) {
				// Validate condition and match it
				list($controller, $method) = explode('@', $condition);
				if($controller && $method) {
					if($this->isRuleAMatchFor($controller, $method)) {
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

    /**
     * Iterates over custom regex rules and compares them with the
     * classPath and method given by the parameter
     *
     * @param $classPathAndMethod
     * @return array
     */
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

    /**
     * Checks rule controller and action against actual request
     * Also takes care of globs
     *
     * @param $ruleController
     * @param $ruleAction
     * @return bool
     */
    private function isRuleAMatchFor($ruleController, $ruleAction) {
		if(($ruleController === $this->requestParser->getControllerName() || $ruleController === '*') &&
			($ruleAction === $this->requestParser->getControllerMethod() || $ruleAction === '*')) {
			return true;
		}
		return false;
	}

    /**
     * Any errors?
     *
     * @return bool
     */
    public function hasErrors() {
		if(count($this->errors) > 0) {
			return true;
		}
		return false;
	}

	public function getErrors() {
		return $this->errors;
	}

    /**
     * Check if config defines a default no permission route
     *
     * @return bool
     */
    public function hasNoPermissionRoute() {
		if($this->permissionConf !== null && $this->permissionConf['defaultNoPermissionRoute'] !== 'NONE') {
			return true;
		}
		return false;
	}

    /**
     * Returns no permission route
     *
     * @return string
     */
    public function getNoPermissionRoute() {
		if($this->permissionConf !== null) {
			return $this->permissionConf['defaultNoPermissionRoute'];
		}
		return '/';
	}

    /**
     * Check if app is in testing mode and therefore allows permissions
     * to be set using the LG_PERMS environment var
     *
     *
     * @return bool
     */
    public function isAppInTestingMode() {
        if($this->permissionConf !== null
            && $this->permissionConf['testing']['appEnv'] === env('APP_ENV')) {
            return true;
        }
        return false;
    }


    /**
     * Log some debug information?
     */
    public function debugging()
    {
        if ($this->permissionConf !== null) {
            if($this->permissionConf['debug']) {
                return true;
            } else if($this->isAppInTestingMode() &&
                $this->permissionConf['testing']['debug']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if App in testing mode and returns default testing permissions as
     * well as temporary permissions
     *
     * @return array
     */
    public function getTestingModePermissions() {
        if($this->permissionConf !== null
            && $this->permissionConf['testing']['appEnv'] === env('APP_ENV')) {
            // Get default permissions for testing mode
            $permissions = explode(',',$this->permissionConf['testing']['defaultPermissions']);
            // Get temporary permissions
            $temporaryPermissions = $this->getTemporaryPermissions();
            $permissions = array_merge($permissions, $temporaryPermissions);
            return $permissions;
        }
        return [];
    }


    /**
     * Returns temporary permissions and updates the request counter,
     * so don't call it multiple times in one request, it's private anyway.
     *
     * @return array
     */
    private function getTemporaryPermissions() {
        // Check for temporary permissions
        if($this->permissionConf['testing']['temporaryPermissions'] !== null) {
            $temporaryPermissions = explode(',', $this->permissionConf['testing']['temporaryPermissions']);
            // Check request counter: > 0 allows temporary permissions, -1 always allows temporary permissions
            if($this->permissionConf['testing']['temporaryRequestCounter'] > 0) {
                // Update temporary request counter: -1
                $newCounter = $this->permissionConf['testing']['temporaryRequestCounter'] - 1;
                // Write new permission conf
                if($this->setTemporaryPermissions($temporaryPermissions, $newCounter)) {
                    return $temporaryPermissions;
                } else {
                    // Writing temporary permissions failed, return empty array
                    return [];
                }
            } else if($this->permissionConf['testing']['temporaryRequestCounter'] === -1) {
                // -1 means always allow temporary permissions
                return $temporaryPermissions;
            }
        }
        return [];
    }

    /**
     * Resets the temporaryPermissions to null
     *
     * @return bool Success or not
     */
    public function resetTemporaryPermissions() {
        return $this->setTemporaryPermissions([], 0);
    }

    /**
     * Set temporary permissions for testing purposes
     *
     * @param $permissionArray
     * @param int $requestLifetime
     * @return bool
     */
    public function setTemporaryPermissions($permissionArray, $requestLifetime = 2) {
        // Only allow if app is in testing mode
        if(! $this->isAppInTestingMode()) return false;
        // Check for parsing errors before we try to write permissions.yml
        if($this->hasErrors()) {
            \Log::error('[Laraguard] ERROR prevented writing of temporary permissions: '.join(' # ',$this->getErrors()));
            return false;
        }
        $newPermissionConf = $this->permissionConfOriginal;
        // Set permissions
        $newPermissionConf['testing']['temporaryPermissions'] = join(',',$permissionArray);
        // Set request counter
        $newPermissionConf['testing']['temporaryRequestCounter'] = $requestLifetime;
        // Write back to permission.yml
        $yaml = Yaml::dump($newPermissionConf, 5);
        // Write new yaml file
        $result = file_put_contents($this->permissionPath, $yaml);
        if($result !== false) {
            return true;
        }
        \Log::error('[Laraguard] ERROR - could not write permissions.yml');
        return false;
    }
}