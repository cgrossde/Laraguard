<?php namespace CGross\Laraguard\Services;

use CGross\Laraguard\PermissionParser;

class Laraguard {
    /**
     * @var PermissionParser
     */
    protected $permissionParser;

    /**
     * Init permission parser and load permissions.yml
     */
    public function __construct() {
        $this->permissionParser = new PermissionParser();
        // Try to load permissions
        if(! $this->permissionParser->loadPermissions()) {
            $errors = $this->permissionParser->getErrors();
            \Log::error('[Laraguard] ERROR - loading of permissions failed: '.join(' # ', $errors));
        }
    }

    /**
     * Set temporary permissions with a request lifetime (int)
     * After each request this lifetime will be decreased by one
     * until it reaches zero. If you set -1 the temporary permission
     * will become permanent (until reset)
     *
     * @param array $permissionArray
     * @param int $permissionLifetime
     * @return bool
     */
    public function setTemporaryPermissions($permissionArray, $permissionLifetime = -1) {
        return $this->permissionParser->setTemporaryPermissions($permissionArray, $permissionLifetime);
    }

    /**
     * Reset temporary permission to null
     * @return bool
     */
    public function resetTemporaryPermissions() {
        return $this->permissionParser->resetTemporaryPermissions();
    }
}