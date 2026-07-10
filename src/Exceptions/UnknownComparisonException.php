<?php

declare(strict_types=1);

namespace Syriable\Metrics\Exceptions;

final class UnknownComparisonException extends MetricsException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf(
            'Unknown comparison strategy [%s]. Register it with Metrics::registerComparison().',
            $key,
        ));
    }
}
