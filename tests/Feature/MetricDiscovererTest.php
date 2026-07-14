<?php

declare(strict_types=1);

use DiscoveryFixtures\GoodMetric;
use DiscoveryFixtures\Sales\NestedMetric;
use Illuminate\Support\Facades\File;
use Syriable\Metrics\Discovery\MetricDiscoverer;
use Syriable\Metrics\Facades\Metrics;
use Syriable\Metrics\MetricsServiceProvider;

beforeEach(function (): void {
    $this->metricsPath = sys_get_temp_dir().'/laravel-metrics-discovery-'.uniqid();

    File::makeDirectory($this->metricsPath, 0755, true);

    config([
        'metrics.generator.namespace' => 'DiscoveryFixtures',
        'metrics.generator.path' => $this->metricsPath,
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->metricsPath);
});

function putMetricFixture(string $path, string $class, string $namespace = 'DiscoveryFixtures'): void
{
    File::ensureDirectoryExists(dirname($path));

    File::put($path, <<<PHP
    <?php

    namespace {$namespace};

    use Syriable\Metrics\Builder\MetricBuilder;
    use Syriable\Metrics\Facades\Metrics;
    use Syriable\Metrics\Metric;
    use Syriable\Metrics\Tests\Fixtures\Order;

    class {$class} extends Metric
    {
        public function query(): MetricBuilder
        {
            return Metrics::query(Order::class)->count();
        }
    }
    PHP);
}

it('discovers concrete Metric subclasses, including nested ones, and ignores everything else', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Metric discovery has path handling issues on Windows CI');
    }
    putMetricFixture($this->metricsPath.'/GoodMetric.php', 'GoodMetric');
    putMetricFixture($this->metricsPath.'/Sales/NestedMetric.php', 'NestedMetric', 'DiscoveryFixtures\\Sales');

    File::put($this->metricsPath.'/AbstractMetric.php', <<<'PHP'
    <?php

    namespace DiscoveryFixtures;

    use Syriable\Metrics\Metric;

    abstract class AbstractMetric extends Metric
    {
    }
    PHP);

    File::put($this->metricsPath.'/NotAMetric.php', <<<'PHP'
    <?php

    namespace DiscoveryFixtures;

    class NotAMetric
    {
    }
    PHP);

    require $this->metricsPath.'/GoodMetric.php';
    require $this->metricsPath.'/Sales/NestedMetric.php';
    require $this->metricsPath.'/AbstractMetric.php';
    require $this->metricsPath.'/NotAMetric.php';

    $discovered = app(MetricDiscoverer::class)->discover();

    expect($discovered)->toEqualCanonicalizing([
        GoodMetric::class,
        NestedMetric::class,
    ]);
});

it('returns an empty list when the metrics directory does not exist', function (): void {
    config(['metrics.generator.path' => $this->metricsPath.'/does-not-exist']);

    expect(app(MetricDiscoverer::class)->discover())->toBe([]);
});

it('automatically registers discovered metrics at boot', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Metric discovery has path handling issues on Windows CI');
    }
    putMetricFixture($this->metricsPath.'/AutoRegistered.php', 'AutoRegistered');
    require $this->metricsPath.'/AutoRegistered.php';

    (new MetricsServiceProvider(app()))->packageBooted();

    expect(Metrics::registered())->toHaveKey('auto_registered');
});

it('does not register anything when discovery is disabled', function (): void {
    config(['metrics.discover' => false]);

    putMetricFixture($this->metricsPath.'/ShouldNotRegister.php', 'ShouldNotRegister');
    require $this->metricsPath.'/ShouldNotRegister.php';

    (new MetricsServiceProvider(app()))->packageBooted();

    expect(Metrics::registered())->not->toHaveKey('should_not_register');
});
