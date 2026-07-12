<?php

declare(strict_types=1);

namespace Syriable\Metrics\Engine;

use Carbon\CarbonImmutable;
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
     * @return array{0: bool, 1: array<string, mixed>, 2: CarbonImmutable} [cache hit, payload, generated at]
     */
    public function remember(
        string $key,
        DateInterval|DateTimeInterface|int $ttl,
        Closure $compute,
        CarbonImmutable $now,
    ): array {
        $store = $this->store();

        $cached = $store->get($key);

        if (is_array($cached) && isset($cached['payload'], $cached['generated_at'])) {
            return [true, $cached['payload'], CarbonImmutable::parse($cached['generated_at'])];
        }

        if (is_array($cached)) {
            return [true, $cached, $now];
        }

        $payload = $compute();

        $store->put($key, [
            'payload' => $payload,
            'generated_at' => $now->toIso8601String(),
        ], $ttl);

        return [false, $payload, $now];
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
