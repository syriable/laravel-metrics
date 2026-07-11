<?php

declare(strict_types=1);

namespace Syriable\Metrics\Console\Generators;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;
use Syriable\Metrics\Contracts\MetricBlueprint;
use Syriable\Metrics\Exceptions\InvalidMetricNameException;
use Syriable\Metrics\Metric;

/**
 * Orchestrates one metric class generation:
 *
 *   validate the name → resolve its namespace and file path
 *   → render the blueprint's stub → write the file (unless it already
 *   exists and isn't forced).
 *
 * Nothing here talks to the console — MetricMakeCommand owns the CLI
 * signature, options and output; this class is safe to call from a test,
 * a job, or another command.
 */
final readonly class MetricGenerator
{
    public function __construct(
        private ConfigRepository $config,
        private NamespaceResolver $namespaces,
        private PathResolver $paths,
        private StubResolver $stubs,
        private FileWriter $files,
    ) {}

    public function generate(string $name, MetricBlueprint $blueprint, bool $force = false): GeneratorResult
    {
        if (trim($name) === '') {
            throw InvalidMetricNameException::empty();
        }

        if (! $this->namespaces->isValidName($name)) {
            throw InvalidMetricNameException::forName($name);
        }

        $class = $this->namespaces->classBasename($name);
        $namespace = $this->namespaces->namespaceFor($name);
        $path = $this->paths->resolve($name);

        if (! $force && $this->files->exists($path)) {
            return GeneratorResult::alreadyExists($path, $class, $namespace);
        }

        $baseClass = ltrim((string) $this->config->get('metrics.generator.base_class', Metric::class), '\\');

        $contents = $this->stubs->populate($blueprint, [
            'namespace' => $namespace,
            'class' => $class,
            'namespacedBaseClass' => $baseClass,
            'baseClass' => Str::afterLast($baseClass, '\\'),
        ]);

        $this->files->write($path, $contents);

        return GeneratorResult::created($path, $class, $namespace);
    }
}
