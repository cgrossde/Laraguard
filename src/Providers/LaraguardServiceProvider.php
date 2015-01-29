<?php namespace CGross\Laraguard\Providers;

use CGross\Laraguard\Services\Laraguard;
use Illuminate\Support\ServiceProvider;

class LaraguardServiceProvider extends ServiceProvider {

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('Laraguard', function($app) {
            return new Laraguard();
        });
        $this->app->singleton('CGross\Laraguard\Services\Laraguard', function($app) {
           return $app->make('Laraguard');
        });
    }

}