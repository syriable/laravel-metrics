<?php

declare(strict_types=1);

namespace Syriable\Metrics;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MetricsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-metrics')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Metrics::class, static function (Application $app): Metrics {
            return new Metrics(
                $app->make(CacheFactory::class),
                (array) $app['config']->get('metrics', []),
            );
        });
    }
}
