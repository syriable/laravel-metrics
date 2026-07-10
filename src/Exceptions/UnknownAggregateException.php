<?php

declare(strict_types=1);

namespace Syriable\Metrics\Exceptions;

final class UnknownAggregateException extends MetricsException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf(
            'Unknown aggregate [%s]. Register it with Metrics::registerAggregate().',
            $key,
        ));
    }
}
