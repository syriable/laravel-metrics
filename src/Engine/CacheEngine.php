<?php

declare(strict_types=1);

namespace Syriable\Metrics\Engine;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;

/**
 * Whole-computation caching with deterministic, content-derived keys.
 *
 * Keys are hashed from everything that influences the numbers — the
 * compiled SQL and bindings of every dataset, the resolved period,
 * interval, timezone, formulas and comparison — so two metrics that would
 * run identical queries share an entry, and any definition change is
 * automatically a cache miss. Only plain arrays are stored; result objects
 * and closures never enter the cache.
 */
final readonly class CacheEngine
{
    /**
     * @param  array{store: ?string, prefix: string}  $config
     */
    public function __construct(
        private CacheFactory $cache,
        private array $config,
    ) {}

    /**
     * @param  array<array-key, mixed>  $parts
     */
    public function key(array $parts): string
    {
        return $this->config['prefix'].':'.hash('sha256', (string) json_encode($parts));
    }

    /**
     * @param  Closure(): array<string, mixed>  $compute
     * @return array{0: bool, 1: array<string, mixed>} [cache hit, payload]
     */
    public function remember(string $key, DateInterval|DateTimeInterface|int $ttl, Closure $compute): array
    {
        $store = $this->store();

        $cached = $store->get($key);

        if (is_array($cached)) {
            return [true, $cached];
        }

        $payload = $compute();

        $store->put($key, $payload, $ttl);

        return [false, $payload];
    }

    public function forget(string $key): bool
    {
        return $this->store()->forget($key);
    }

    private function store(): Repository
    {
        return $this->cache->store($this->config['store']);
    }
}
