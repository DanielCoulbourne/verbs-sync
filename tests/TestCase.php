<?php

namespace DanielCoulbourne\VerbsSync\Tests;

use DanielCoulbourne\VerbsSync\VerbsSyncServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            VerbsSyncServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite in memory
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up Verbs Sync config
        $app['config']->set('verbs-sync.source.url', 'http://example.com/api');
        $app['config']->set('verbs-sync.source.api_token', 'test-token');
        $app['config']->set('verbs-sync.events.include', ['*']);
        $app['config']->set('verbs-sync.events.exclude', []);
        $app['config']->set('verbs-sync.options.batch_size', 10);
        $app['config']->set('verbs-sync.options.retry_attempts', 1);

        // Set up environment variables
        $app['config']->set('app.env', 'testing');
        $app->loadEnvironmentFrom('.env.testing');

        // Environment variables that would normally be in .env
        $_ENV['VERBS_SYNC_SOURCE_URL'] = 'http://example.com/api';
        $_ENV['VERBS_SYNC_API_TOKEN'] = 'test-token';
    }

    /**
     * Create test tables for verbs events.
     *
     * @return void
     */
    protected function createVerbsTables()
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('verbs_events', function ($table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->json('data');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }
}
