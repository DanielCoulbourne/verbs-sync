<?php

namespace DanielCoulbourne\VerbsSync;

use DanielCoulbourne\VerbsSync\Commands\PullEventsCommand;
use Illuminate\Support\ServiceProvider;

class VerbsSyncServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/verbs-sync.php' => config_path('verbs-sync.php'),
        ], 'config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \DanielCoulbourne\VerbsSync\Commands\PullEventsCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/verbs-sync.php', 'verbs-sync'
        );

        // Register the main service class
        $this->app->singleton('verbs-sync', function ($app) {
            return new VerbsSync($app);
        });

        // Register the event processor
        $this->app->singleton(EventProcessor::class, function ($app) {
            return new EventProcessor();
        });

        // Register the event repository
        $this->app->singleton(EventRepository::class, function ($app) {
            return new EventRepository();
        });

        // Prevent Laravel from trying to load a non-existent command class
        if (class_exists('DanielCoulbourne\VerbsSync\Commands\VerbsSync')) {
            $this->app->bind('DanielCoulbourne\VerbsSync\Commands\VerbsSync', function ($app) {
                return $app->make(\DanielCoulbourne\VerbsSync\Commands\PullEventsCommand::class);
            });
        }
    }
}
