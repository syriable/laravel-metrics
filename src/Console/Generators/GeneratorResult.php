<?php

declare(strict_types=1);

namespace Syriable\Metrics\Console\Generators;

/**
 * The outcome of one MetricGenerator::generate() call — enough for
 * MetricMakeCommand to render its console output without knowing anything
 * about how the file was produced.
 */
final readonly class GeneratorResult
{
    private function __construct(
        public bool $created,
        public string $path,
        public string $class,
        public string $namespace,
    ) {}

    public static function created(string $path, string $class, string $namespace): self
    {
        return new self(true, $path, $class, $namespace);
    }

    public static function alreadyExists(string $path, string $class, string $namespace): self
    {
        return new self(false, $path, $class, $namespace);
    }
}
