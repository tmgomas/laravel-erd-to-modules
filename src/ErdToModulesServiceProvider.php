<?php

namespace YourName\ErdToModules;

use Illuminate\Support\ServiceProvider;
use YourName\ErdToModules\Commands\GenerateFromErd;
use YourName\ErdToModules\Services\ErdParser;
use YourName\ErdToModules\Services\ModuleGenerator;

class ErdToModulesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/erd-to-modules.php', 'erd-to-modules'
        );

        $this->app->singleton('erd-parser', function () {
            return new ErdParser();
        });

        $this->app->singleton('module-generator', function ($app) {
            return new ModuleGenerator(
                $app->make('erd-parser'),
                $app['config']->get('erd-to-modules.paths', []),
                $app['files']
            );
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publishing config
        $this->publishes([
            __DIR__.'/../config/erd-to-modules.php' => config_path('erd-to-modules.php'),
        ], 'erd-to-modules-config');

        // Publishing stubs
        $this->publishes([
            __DIR__.'/../resources/stubs' => resource_path('stubs/vendor/erd-to-modules'),
        ], 'erd-to-modules-stubs');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateFromErd::class,
            ]);
        }
    }
}