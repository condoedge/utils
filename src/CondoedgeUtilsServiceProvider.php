<?php

namespace Condoedge\Utils;

use Condoedge\Utils\Facades\FileModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Condoedge\Utils\Kompo\Common\Modal;
use Condoedge\Utils\Kompo\Common\Query;
use Condoedge\Utils\Kompo\Common\Table;
use Illuminate\Support\ServiceProvider;
use Condoedge\Utils\Kompo\Plugins\HasScroll;
use Condoedge\Utils\Kompo\Plugins\ExportPlugin;
use Condoedge\Utils\Services\DataStructures\Graph;
use Illuminate\Database\Eloquent\Relations\Relation;
use Condoedge\Utils\Kompo\Plugins\Base\PluginsManager;
use Condoedge\Utils\Kompo\Plugins\EnableResponsiveTable;
use Condoedge\Utils\Kompo\Plugins\EnableWhiteTableStyle;
use Condoedge\Utils\Services\GlobalConfig\GlobalConfigServiceContract;

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

        $this->publishes([
            __DIR__ . '/../config/global-config.php' => config_path('global-config.php'),
            __DIR__ . '/../config/kompo-utils.php' => config_path('kompo-utils.php'),
            __DIR__ . '/../config/kompo-files.php' => config_path('kompo-files.php'),
            __DIR__ . '/../config/kompo-tags.php' => config_path('kompo-tags.php'),
        ], 'kompo-utils-config');

        $this->app->bind(FILE_MODEL_KEY, function () {
            return new (config('kompo-utils.file-model-namespace'));
        });

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
            __DIR__ . '/../resources/js' => base_path('resources/js/utils'),
        ], 'utils-assets');

        Query::setPlugins([
            ExportPlugin::class,
        ]);

        Table::setPlugins([
            EnableWhiteTableStyle::class,
            EnableResponsiveTable::class,
        ]);

        Modal::setPlugins([
            HasScroll::class,
        ]);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(PluginsManager::class, function ($app) {
            return new PluginsManager();
        });

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

        $this->app->bind('note-model', function () {
            return new (config('kompo-utils.note-model-namespace'));
        });

        $this->app->bind('team-model', function () {
            return new (config('kompo-utils.team-model-namespace'));
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
            'kompo-files' => __DIR__.'/../config/kompo-files.php',
            'kompo-utils' => __DIR__.'/../config/kompo-utils.php',
            'kompo-tags' => __DIR__.'/../config/kompo-tags.php',
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
            'file' => FileModel::getClass(),
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
