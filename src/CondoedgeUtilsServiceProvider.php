<?php

namespace Condoedge\Utils;

use Condoedge\Utils\Command\SendEmailForMissingTranslationsCommand;
use Condoedge\Utils\Facades\FileModel;
use Condoedge\Utils\Kompo\Common\Form;
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
use Condoedge\Utils\Kompo\Plugins\TableIntoFormSetValuesPlugin;
use Condoedge\Utils\Services\GlobalConfig\GlobalConfigServiceContract;

use App\Models\User;
use Condoedge\Utils\Command\FixIncompleteAddressesCommand;
use Condoedge\Utils\Command\RunComplianceValidationCommand;
use Condoedge\Utils\Events\MultipleComplianceIssuesDetected;
use Condoedge\Utils\Kompo\Plugins\DebugReload;
use Condoedge\Utils\Kompo\Plugins\HasIntroAnimation;
use Condoedge\Utils\Listeners\HandleBatchComplianceNotifications;
use Condoedge\Utils\Services\ComplianceValidation\NotificationStrategyRegistry;
use Condoedge\Utils\Services\Maps\GeocodioService;
use Condoedge\Utils\Services\Maps\GoogleMapsService;
use Condoedge\Utils\Services\Maps\NominatimService;
use Illuminate\Support\Facades\Event;
use Condoedge\Utils\Command\MissingTranslationAnalyzerCommand;

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

        if (config('kompo-utils.load-migrations', true)) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        $this->publishes([
            __DIR__ . '/../config/global-config.php' => config_path('global-config.php'),
            __DIR__ . '/../config/kompo-utils.php' => config_path('kompo-utils.php'),
            __DIR__ . '/../config/kompo-files.php' => config_path('kompo-files.php'),
            __DIR__ . '/../config/kompo-tags.php' => config_path('kompo-tags.php'),
        ], 'kompo-utils-config');

        $this->publishes([
            __DIR__ . '/../resources/icons' => public_path('icons'),
        ], 'kompo-utils-icons');

        $this->app->bind(FILE_MODEL_KEY, function () {
            return new (config('kompo-utils.file-model-namespace'));
        });

        $this->loadRelationsMorphMap();
        $this->loadCommands();
        $this->scheduleCommands();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'kompo-utils');

        $this->publishes([
            __DIR__ . '/../resources/js' => base_path('resources/js/utils'),
        ], 'utils-assets');

        Query::setPlugins([
            ExportPlugin::class,
            HasIntroAnimation::class,
            EnableWhiteTableStyle::class,
        ]);

        Table::setPlugins([
            ExportPlugin::class,
            EnableWhiteTableStyle::class,
            EnableResponsiveTable::class,
            TableIntoFormSetValuesPlugin::class,
            HasIntroAnimation::class,
        ]);

        Form::setPlugins([
            DebugReload::class,
            HasIntroAnimation::class,
        ]);

        Modal::setPlugins([
            HasScroll::class,
            DebugReload::class,
            HasIntroAnimation::class,
        ]);

        $this->registerComplianceNotificationSystem();
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        if (!$this->app->runningInConsole()) {
            // Extend the translator to track missing translations
            $this->app->extend('translator', function ($translator, $app) {
                $loader = $translator->getLoader();
                $locale = $translator->getLocale();

                $trackingTranslator = new \Condoedge\Utils\Services\Translation\TrackingTranslator($loader, $locale);
                $trackingTranslator->setFallback($translator->getFallback());
                
                return $trackingTranslator;
            });
        }
        
        $this->booted(function () {
            \Route::middleware('web')->group(__DIR__.'/../routes/files.php');
            \Route::middleware('web')->group(__DIR__.'/../routes/utils.php');
        });

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

        $this->app->bind('user-model', function () {
            return new (config('kompo-auth.user-model', config('kompo-utils.user-model-namespace', User::class)));
        });

        $this->app->singleton(GoogleMapsService::class, function ($app) {
            return new GoogleMapsService(
                apiKey: config('services.google_maps.api_key')
            );
        });

        $this->app->singleton(NominatimService::class, function ($app) {
            return new NominatimService();
        });

        $this->app->singleton(GeocodioService::class, function ($app) {
            return new GeocodioService(
                apiKey: config('services.geocodio.api_key')
            );
        });

        $this->app->bind(\Condoedge\Utils\Services\Maps\GeocodingService::class, function ($app) {
            return $app->make(NominatimService::class);
        });

        // Register compliance notification system
        $this->app->singleton(NotificationStrategyRegistry::class, function ($app) {
            return new NotificationStrategyRegistry();
        });

        // Note: ComplianceNotificationService is abstract and must be bound in the consuming project
        // Example in your main project's service provider:
        // $this->app->bind(ComplianceNotificationService::class, YourConcreteNotificationService::class);
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
                RunComplianceValidationCommand::class,
                FixIncompleteAddressesCommand::class,
                MissingTranslationAnalyzerCommand::class,
                SendEmailForMissingTranslationsCommand::class,
            ]);
        }
    }

    public function scheduleCommands()
    {
        $this->app->booted(function () {
            $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

            $schedule->command('app:missing-translation-analyzer-command')->dailyAt('07:30');
            $schedule->command('app:send-missing-translations-email')->dailyAt('08:00');

            // Option 1: Frequency-based scheduling (RECOMMENDED)
            // $schedule->command('compliance:run-validation --frequency=daily')->dailyAt('02:00');
            // $schedule->command('compliance:run-validation --frequency=weekly')->weekly()->mondays()->at('01:00');
            // $schedule->command('compliance:run-validation --frequency=monthly')->monthly()->at('00:30');
            // $schedule->command('compliance:run-validation --frequency=business-days')->weekdays()->at('08:00');

            // Option 2: Minute-based checking (use if you need precise timing)
            // $schedule->command('compliance:run-validation --scheduled')->everyMinute();

            // Option 3: Hourly checks (good compromise)
            $schedule->command('compliance:run-validation --scheduled')->hourly();
        });
    }

    /**
     * Register the compliance notification system event listeners and strategies
     */
    protected function registerComplianceNotificationSystem(): void
    {
        // Register event listeners
        // Event::listen(ComplianceIssueDetected::class, HandleComplianceNotifications::class);
        
        // Register batch compliance notifications listener
        Event::listen(MultipleComplianceIssuesDetected::class, HandleBatchComplianceNotifications::class);
    }
}
