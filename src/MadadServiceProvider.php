<?php

namespace Madad\Sdk;

use Illuminate\Support\ServiceProvider;
use Madad\Sdk\Console\SyncAllCommand;

class MadadServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/madad.php', 'madad');

        $this->app->singleton(MadadClient::class, function ($app) {
            $config = $app['config']['madad'];

            return new MadadClient(
                apiKey: $config['api_key'],
                timeout: (int) ($config['timeout'] ?? 30),
            );
        });

        $this->app->alias(MadadClient::class, 'madad');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/madad.php' => config_path('madad.php'),
            ], 'madad-config');

            $this->commands([
                SyncAllCommand::class,
            ]);
        }
    }
}
