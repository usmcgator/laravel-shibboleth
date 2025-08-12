<?php

namespace StudentAffairsUwm\Shibboleth;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Route;

class ShibbolethServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        //   dd('shibbol');

        if (config('shibboleth.emulate_idp') === true) {
            $this->publishes([
                __DIR__ . '/../../resources/views/shibalike/' => resource_path('views/vendor/shibalike'),
            ]);

            $this->loadRoutesFrom(__DIR__ . '/../../routes/shibalike.php');
        }

        $this->publishes([
            __DIR__ . '/../../config/shibboleth.php' => config_path('shibboleth.php'),
        ]);
        $this->loadRoutesFrom(__DIR__ . '/../../routes/shibboleth.php');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        

        $this->app['auth']->provider('shibboleth', function ($app) {
            return new Providers\ShibbolethUserProvider($app['config']['auth.providers.users.model']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides()
    {
        return [];
    }
}
