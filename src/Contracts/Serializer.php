<?php

declare(strict_types=1);

namespace Syriable\Metrics\Contracts;

use Syriable\Metrics\Results\MetricResult;

/**
 * Projects an immutable MetricResult into an API payload.
 *
 * Serialization is a pure, stateless projection — it must not compute
 * domain values (percentages, comparisons, gap-filling all happen in the
 * engine). Swap the serializer globally via Metrics::useSerializer() to
 * reshape every payload without touching the engine.
 */
interface Serializer
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(MetricResult $result): array;
}
