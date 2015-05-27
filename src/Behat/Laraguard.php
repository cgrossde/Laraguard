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
     * Clear temporary permissions after each scenario
     * @BeforeScenario
     */
    public function resetTemporaryPermissions() {
        self::$laraguard->resetTemporaryPermissions();
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