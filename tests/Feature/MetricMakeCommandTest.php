<?php

declare(strict_types=1);

use App\Metrics\LoadableRevenue;
use App\Metrics\User;
use Illuminate\Support\Facades\File;
use Syriable\Metrics\Metric;

beforeEach(function (): void {
    $this->metricsPath = sys_get_temp_dir().'/laravel-metrics-tests-'.uniqid();

    config([
        'metrics.generator.namespace' => 'App\\Metrics',
        'metrics.generator.path' => $this->metricsPath,
        'metrics.generator.stub' => null,
        'metrics.generator.base_class' => Metric::class,
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->metricsPath);
});

it('generates a metric class in the configured namespace and path', function (): void {
    $this->artisan('make:metric', ['name' => 'Revenue'])
        ->assertExitCode(0);

    $path = $this->metricsPath.'/Revenue.php';

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)->toContain('declare(strict_types=1);')
        ->and($contents)->toContain('namespace App\Metrics;')
        ->and($contents)->toContain('class Revenue extends \Syriable\Metrics\Metric');
});

it('supports nested names for subdirectories and sub-namespaces', function (): void {
    $this->artisan('make:metric', ['name' => 'Sales/Revenue'])
        ->assertExitCode(0);

    $path = $this->metricsPath.'/Sales/Revenue.php';

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)->toContain('namespace App\Metrics\Sales;')
        ->and($contents)->toContain('class Revenue extends \Syriable\Metrics\Metric');
});

it('generates a class that actually extends the base Metric class', function (): void {
    $this->artisan('make:metric', ['name' => 'LoadableRevenue'])->assertExitCode(0);

    require $this->metricsPath.'/LoadableRevenue.php';

    expect(class_exists(LoadableRevenue::class))->toBeTrue()
        ->and(is_subclass_of(LoadableRevenue::class, Metric::class))->toBeTrue();
});

it('generates valid, loadable code when the name collides with the placeholder model reference', function (): void {
    $this->artisan('make:metric', ['name' => 'User'])->assertExitCode(0);

    $contents = File::get($this->metricsPath.'/User.php');

    // A `use App\Models\User;` import alongside a same-named `class User`
    // is a fatal "Cannot use ... because the name is already in use" —
    // the placeholder model reference must stay fully qualified inline.
    expect($contents)->not->toContain('use App\Models\User;');

    require $this->metricsPath.'/User.php';

    expect(class_exists(User::class))->toBeTrue()
        ->and(is_subclass_of(User::class, Metric::class))->toBeTrue();
});

it('generates valid, loadable code when the name collides with the base class', function (): void {
    $this->artisan('make:metric', ['name' => 'Metric'])->assertExitCode(0);

    $contents = File::get($this->metricsPath.'/Metric.php');

    expect($contents)->not->toContain('use Syriable\Metrics\Metric;');

    require $this->metricsPath.'/Metric.php';

    expect(class_exists(App\Metrics\Metric::class))->toBeTrue()
        ->and(is_subclass_of(App\Metrics\Metric::class, Metric::class))->toBeTrue();
});

it('refuses to overwrite an existing metric without --force', function (): void {
    $this->artisan('make:metric', ['name' => 'Revenue'])->assertExitCode(0);

    $path = $this->metricsPath.'/Revenue.php';
    File::put($path, '<?php // untouched');

    $this->artisan('make:metric', ['name' => 'Revenue'])->assertExitCode(1);

    expect(File::get($path))->toBe('<?php // untouched');
});

it('overwrites an existing metric with --force', function (): void {
    $this->artisan('make:metric', ['name' => 'Revenue'])->assertExitCode(0);

    $path = $this->metricsPath.'/Revenue.php';
    File::put($path, '<?php // untouched');

    $this->artisan('make:metric', ['name' => 'Revenue', '--force' => true])->assertExitCode(0);

    $contents = File::get($path);

    expect($contents)->toContain('class Revenue extends \Syriable\Metrics\Metric')
        ->and($contents)->not->toContain('untouched');
});

it('rejects an empty name', function (): void {
    $this->artisan('make:metric', ['name' => ''])->assertExitCode(1);
});

it('rejects a malformed name', function (): void {
    $this->artisan('make:metric', ['name' => 'Sales//Revenue'])->assertExitCode(1);

    expect(File::isDirectory($this->metricsPath))->toBeFalse();
});

it('rejects reserved PHP words as class names', function (): void {
    $this->artisan('make:metric', ['name' => 'Class'])->assertExitCode(1);

    expect(File::exists($this->metricsPath.'/Class.php'))->toBeFalse();
});

it('respects a custom base class from configuration', function (): void {
    config(['metrics.generator.base_class' => 'App\\Support\\Metrics\\BaseMetric']);

    $this->artisan('make:metric', ['name' => 'Revenue'])->assertExitCode(0);

    $contents = File::get($this->metricsPath.'/Revenue.php');

    expect($contents)->toContain('class Revenue extends \App\Support\Metrics\BaseMetric');
});

it('respects a custom stub from configuration', function (): void {
    $stub = sys_get_temp_dir().'/laravel-metrics-custom-'.uniqid().'.stub';
    File::put($stub, "<?php\n\nnamespace {{ namespace }};\n\n// custom stub marker\nclass {{ class }} extends {{ baseClass }} {}\n");

    config(['metrics.generator.stub' => $stub]);

    $this->artisan('make:metric', ['name' => 'Revenue'])->assertExitCode(0);

    expect(File::get($this->metricsPath.'/Revenue.php'))->toContain('// custom stub marker');

    File::delete($stub);
});

it('prefers a published stub over the bundled default', function (): void {
    $published = base_path('stubs/metrics/metric.stub');

    File::ensureDirectoryExists(dirname($published));
    File::put($published, "<?php\n\nnamespace {{ namespace }};\n\n// published stub marker\nclass {{ class }} extends {{ baseClass }} {}\n");

    try {
        $this->artisan('make:metric', ['name' => 'Revenue'])->assertExitCode(0);

        expect(File::get($this->metricsPath.'/Revenue.php'))->toContain('// published stub marker');
    } finally {
        File::deleteDirectory(dirname($published));
    }
});
