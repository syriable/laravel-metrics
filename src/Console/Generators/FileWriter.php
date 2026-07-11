<?php

declare(strict_types=1);

namespace Syriable\Metrics\Console\Generators;

use Illuminate\Filesystem\Filesystem;

/**
 * The only class that touches disk when generating a metric — everything
 * else in the generator deals in strings and paths.
 */
final readonly class FileWriter
{
    public function __construct(private Filesystem $files) {}

    public function exists(string $path): bool
    {
        return $this->files->exists($path);
    }

    public function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $this->files->put($path, $contents);
    }
}
