<?php

declare(strict_types=1);

namespace Syriable\Metrics\Serializers;

use Syriable\Metrics\Contracts\Serializer;
use Syriable\Metrics\Results\DatasetResult;
use Syriable\Metrics\Results\MetricResult;

/**
 * The default projection: a flat, predictable, UI-agnostic structure that
 * any REST/GraphQL/mobile consumer can read without knowing this package.
 */
final class ArraySerializer implements Serializer
{
    public function serialize(MetricResult $result): array
    {
        return [
            'key' => $result->key,
            'type' => $result->type->value,
            'range' => [
                'key' => $result->rangeKey,
                'start' => $result->period?->start->toIso8601String(),
                'end' => $result->period?->end->toIso8601String(),
                'timezone' => $result->timezone,
            ],
            'interval' => $result->interval?->value,
            'datasets' => array_map(
                static fn (DatasetResult $dataset) => $dataset->toArray(),
                $result->datasets,
            ),
            'meta' => $result->meta === [] ? (object) [] : $result->meta,
            'generated_at' => $result->generatedAt->toIso8601String(),
            'from_cache' => $result->fromCache,
        ];
    }
}
