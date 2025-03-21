<?php

namespace tmgomas\ErdToModules;

use Illuminate\Support\ServiceProvider;
use tmgomas\ErdToModules\Commands\GenerateFromErd;
use tmgomas\ErdToModules\Services\ErdParser;
use tmgomas\ErdToModules\Services\ModuleGenerator;

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
            __DIR__ . '/../config/erd-to-modules.php',
            'erd-to-modules'
        );

        $this->app->singleton('erd-parser', function () {
            return new ErdParser();
        });

        $this->app->singleton(ErdParser::class, function () {
            return new ErdParser();
        });

        $this->app->singleton('module-generator', function ($app) {
            return new ModuleGenerator(
                $app->make(ErdParser::class),
                $app['config']->get('erd-to-modules.paths', []),
                $app['files']
            );
        });

        $this->app->singleton(ModuleGenerator::class, function ($app) {
            return new ModuleGenerator(
                $app->make(ErdParser::class),
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
            __DIR__ . '/../config/erd-to-modules.php' => config_path('erd-to-modules.php'),
        ], 'erd-to-modules-config');

        // Publishing stubs
        $this->publishes([
            __DIR__ . '/../resources/stubs' => resource_path('stubs/vendor/erd-to-modules'),
        ], 'erd-to-modules-stubs');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateFromErd::class,
            ]);
        }
    }
}
