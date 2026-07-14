<?php

declare(strict_types=1);

namespace Syriable\Metrics\Discovery;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Syriable\Metrics\Metric;

/**
 * Finds every concrete Metric subclass under the generator's configured
 * namespace/path — the same file-path-to-class-name discovery Laravel uses
 * for console commands under app/Console/Commands (Kernel::load()). No
 * manifest, no cache: metrics directories are small, and this runs once
 * per boot.
 */
final readonly class MetricDiscoverer
{
    public function __construct(private ConfigRepository $config) {}

    /**
     * @return list<class-string<Metric>>
     */
    public function discover(): array
    {
        $path = (string) $this->config->get('metrics.generator.path', '');

        if ($path === '' || ! is_dir($path)) {
            return [];
        }

        $namespace = trim((string) $this->config->get('metrics.generator.namespace', 'App\\Metrics'), '\\');
        $realPath = realpath($path);
        if (! is_string($realPath)) {
            return [];
        }
        $root = str_replace('\\', '/', $realPath);
        /** @var array<class-string<Metric>> */
        $metrics = [];

        foreach ((new Finder)->in($path)->files()->name('*.php')->sortByName() as $file) {
            $pathname = str_replace('\\', '/', $file->getPathname());
            $relative = (string) Str::after($pathname, $root.'/');
            $class = $namespace.'\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                $relative,
            );

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isSubclassOf(Metric::class) && ! $reflection->isAbstract()) {
                /** @var class-string<Metric> $class */
                $metrics[] = $class;
            }
        }

        return $metrics;
    }
}
