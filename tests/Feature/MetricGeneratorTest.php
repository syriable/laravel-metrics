<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Syriable\Metrics\Console\Generators\BlueprintRegistry;
use Syriable\Metrics\Console\Generators\MetricGenerator;

beforeEach(function (): void {
    $this->metricsPath = sys_get_temp_dir().'/laravel-metrics-generator-unit-'.uniqid();

    config([
        'metrics.generator.namespace' => 'App\\Metrics',
        'metrics.generator.path' => $this->metricsPath,
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->metricsPath);
});

it('generates a metric directly, with no artisan command involved', function (): void {
    $generator = app(MetricGenerator::class);
    $blueprint = app(BlueprintRegistry::class)->default();

    $result = $generator->generate('DirectRevenue', $blueprint);

    expect($result->created)->toBeTrue()
        ->and($result->class)->toBe('DirectRevenue')
        ->and($result->namespace)->toBe('App\Metrics')
        ->and(File::get($this->metricsPath.'/DirectRevenue.php'))
        ->toContain('class DirectRevenue extends \Syriable\Metrics\Metric');
});

it('reports an existing metric through its result rather than an exception', function (): void {
    $generator = app(MetricGenerator::class);
    $blueprint = app(BlueprintRegistry::class)->default();

    $generator->generate('DirectRevenue', $blueprint);
    $second = $generator->generate('DirectRevenue', $blueprint);

    expect($second->created)->toBeFalse();
});
