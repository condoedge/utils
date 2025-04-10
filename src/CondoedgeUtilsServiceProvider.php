<?php

namespace Condoedge\Utils;

use Condoedge\Utils\Services\DataStructures\Graph;
use Condoedge\Utils\Services\GlobalConfig\GlobalConfigServiceContract;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;

class CondoedgeUtilsServiceProvider extends ServiceProvider
{
    use \Kompo\Routing\Mixins\ExtendsRoutingTrait;

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadHelpers();
        $this->loadConfig();

        $this->extendRouting(); //otherwise Route::layout doesn't work

        $this->loadJSONTranslationsFrom(__DIR__.'/../resources/lang');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadRelationsMorphMap();
        $this->loadCommands();

        // This will log the missing translation keys in the log file
        app('translator')->handleMissingKeysUsing(function ($key) {
            $hasTranslatableSyntax = preg_match('/^([a-zA-Z]*\.[a-zA-Z]*)+$/', $key);

            if ($hasTranslatableSyntax) {
                Log::warning("MISSING TRANSLATION KEY: $key");
            }
        });

        $this->publishes([
            __DIR__ . '../resources/js' => public_path('js/utils'),
        ], 'utils-assets');
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Register services for integrity checking
        $this->app->bind('finance.graph', function ($app) {
            return new Graph();
        });

        $this->app->singleton(GlobalConfigServiceContract::class, function ($app) {
            $driver = config('services.global_config_service.driver');

            $driverConfig = config("services.global_config_service.drivers.{$driver}");

            if (!$driverConfig) {
                throw new \Exception("The driver {$driver} is not defined in the global config service configuration.");
            }

            $driverClass = $driverConfig['class'];

            return new $driverClass();
        });
    }

    protected function loadHelpers()
    {
        $helpersDir = __DIR__.'/Helpers';

        $autoloadedHelpers = collect(File::allFiles($helpersDir))->map(fn($file) => $file->getRealPath());

        $packageHelpers = [
        ];

        $autoloadedHelpers->concat($packageHelpers)->each(function ($path) {
            if (file_exists($path)) {
                require_once $path;
            }
        });
    }

    protected function loadConfig()
    {
        $dirs = [
            'global-config' => __DIR__.'/../config/global-config.php',
            'services' => __DIR__.'/../config/services.php',
        ];

        foreach ($dirs as $key => $path) {
            $this->mergeConfigFrom($path, $key);
        }
    }
    
    /**
     * Loads a relations morph map.
     */
    protected function loadRelationsMorphMap()
    {
        Relation::morphMap([

        ]);
    }

    public function loadCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                
            ]);
        }
    }
}
