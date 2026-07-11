<?php

declare(strict_types=1);

namespace Syriable\Metrics;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Metrics\Console\Commands\MetricMakeCommand;
use Syriable\Metrics\Console\Generators\BlueprintRegistry;

class MetricsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-metrics')
            ->hasConfigFile()
            ->hasCommand(MetricMakeCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Metrics::class, static function (Application $app): Metrics {
            return new Metrics(
                $app->make(CacheFactory::class),
                (array) $app['config']->get('metrics', []),
            );
        });

        $this->app->singleton(BlueprintRegistry::class);
    }

    public function packageBooted(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            dirname(__DIR__).'/stubs' => base_path('stubs/metrics'),
        ], 'laravel-metrics-stubs');
    }
}
