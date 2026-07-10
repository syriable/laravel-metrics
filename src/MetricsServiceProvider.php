<?php

namespace Syriable\Metrics;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Metrics\Commands\MetricsCommand;

class MetricsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-metrics')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_metrics_table')
            ->hasCommand(MetricsCommand::class);
    }
}
