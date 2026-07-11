<?php

declare(strict_types=1);

namespace Syriable\Metrics\Console\Generators;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;

/**
 * Maps a metric name to the absolute file path it should be written to,
 * mirroring the namespace segments below the configured root as directory
 * segments below the configured metrics path — the PSR-4 convention every
 * first-party Laravel generator follows.
 */
final readonly class PathResolver
{
    public function __construct(
        private ConfigRepository $config,
        private NamespaceResolver $namespaces,
    ) {}

    /**
     * The configured directory generated metrics are written under.
     */
    public function basePath(): string
    {
        return rtrim((string) $this->config->get('metrics.generator.path', app_path('Metrics')), '/\\');
    }

    /**
     * The absolute file path for the given metric name.
     */
    public function resolve(string $name): string
    {
        $qualified = $this->namespaces->qualifyClass($name);
        $relative = Str::after($qualified, $this->namespaces->rootNamespace());
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, ltrim($relative, '\\'));

        return $this->basePath().DIRECTORY_SEPARATOR.$relative.'.php';
    }
}
