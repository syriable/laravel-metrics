<?php

declare(strict_types=1);

namespace Syriable\Metrics;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Metrics\Console\Commands\MetricMakeCommand;
use Syriable\Metrics\Console\Generators\BlueprintRegistry;
use Syriable\Metrics\Discovery\MetricDiscoverer;

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
                (array) $app->make(Repository::class)->get('metrics', []),
            );
        });

        $this->app->singleton(BlueprintRegistry::class);
    }

    public function packageBooted(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                dirname(__DIR__).'/stubs' => base_path('stubs/metrics'),
            ], 'laravel-metrics-stubs');
        }

        if ((bool) $this->app->make(Repository::class)->get('metrics.discover', true)) {
            $this->registerDiscoveredMetrics();
        }
    }

    private function registerDiscoveredMetrics(): void
    {
        $metrics = $this->app->make(MetricDiscoverer::class)->discover();

        if ($metrics !== []) {
            $this->app->make(Metrics::class)->register(...$metrics);
        }
    }
}
