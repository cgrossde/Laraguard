<?php namespace CGross\Laraguard\Behat;

trait Laraguard {

    /**
     * @var \CGross\Laraguard\Services\Laraguard
     */
    protected static $laraguard;

    /**
     * @BeforeSuite
     */
    public static function initLaraguard() {
        self::$laraguard = \App::make('Laraguard');
    }

    /**
     * Clear temporary permissions before each scenario
     * Also forget about denied requests
     * @BeforeScenario
     */
    public function resetTemporaryPermissions() {
        self::$laraguard->resetTemporaryPermissions();
        \Session::forget('laraguard_lastDenied');
        \Session::forget('laraguard_lastDeniedLifetime');
    }

    /**
     * @Given I have the permission :permission
     * @Given I have the permission :permission for :lifetime requests
     */
    public function iHaveThePermission($permission, $lifetime = -1)
    {
        self::$laraguard->setTemporaryPermissions([$permission], $lifetime);
    }

    /**
     * @Given I have the permissions :permission
     * @Given I have the permissions :permission for :lifetime requests
     */
    public function iHaveThePermissions($permissions, $lifetime = -1)
    {
        self::$laraguard->setTemporaryPermissions(explode(',',$permissions), $lifetime);
    }

}