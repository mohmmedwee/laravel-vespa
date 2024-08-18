<?php

namespace YourVendor\Vespa;

use Illuminate\Support\ServiceProvider;

class VespaServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Merge the default configuration with the user's configuration
        $this->mergeConfigFrom(
            __DIR__.'/Config/vespa.php', 'vespa'
        );

        // Register the VespaClient as a singleton in the service container
        $this->app->singleton(VespaClient::class, function ($app) {
            return new VespaClient();
        });

        // Optionally register a facade for VespaClient
        $this->app->alias(VespaClient::class, 'vespa');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish the configuration file if running in the console
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/Config/vespa.php' => config_path('vespa.php'),
            ], 'config');

            // Register Artisan commands if any
            // $this->commands([
            //     \YourVendor\Vespa\Console\SomeCommand::class,
            // ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [VespaClient::class];
    }
}
