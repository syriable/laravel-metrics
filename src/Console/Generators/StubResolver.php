<?php

declare(strict_types=1);

namespace Syriable\Metrics\Console\Generators;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Syriable\Metrics\Contracts\MetricBlueprint;
use Syriable\Metrics\Exceptions\StubNotFoundException;

/**
 * Loads a blueprint's stub — respecting the developer's override chain —
 * and renders it into final class contents.
 *
 * Resolution order: an explicit `metrics.generator.stub` config path, then
 * a published stub at `stubs/metrics/{name}` in the application root, then
 * the blueprint's own bundled default. This is the same publish-and-override
 * convention Laravel's first-party generators use for their own stubs.
 */
final readonly class StubResolver
{
    public function __construct(
        private ConfigRepository $config,
        private Filesystem $files,
    ) {}

    /**
     * The stub file this blueprint will actually render from.
     */
    public function pathFor(MetricBlueprint $blueprint): string
    {
        $configured = $this->config->get('metrics.generator.stub');

        if (is_string($configured) && $configured !== '' && $this->files->exists($configured)) {
            return $configured;
        }

        $published = $this->publishedPath($blueprint);

        if ($this->files->exists($published)) {
            return $published;
        }

        return $blueprint->stubPath();
    }

    /**
     * Load the blueprint's stub and replace its placeholders.
     *
     * @param  array<string, string>  $replacements
     */
    public function populate(MetricBlueprint $blueprint, array $replacements): string
    {
        $path = $this->pathFor($blueprint);

        if (! $this->files->exists($path)) {
            throw StubNotFoundException::atPath($path);
        }

        $stub = $this->files->get($path);

        foreach ($replacements as $search => $value) {
            $stub = str_replace('{{ '.$search.' }}', $value, $stub);
            $stub = str_replace('{{'.$search.'}}', $value, $stub);
        }

        return $stub;
    }

    /**
     * Where this blueprint's stub lands once published via
     * `vendor:publish --tag="laravel-metrics-stubs"`.
     */
    public function publishedPath(MetricBlueprint $blueprint): string
    {
        return base_path('stubs/metrics/'.basename($blueprint->stubPath()));
    }
}
