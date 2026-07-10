<?php

declare(strict_types=1);

namespace Syriable\Metrics\Exceptions;

final class UnknownRangeException extends MetricsException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf(
            'Unknown range [%s]. Use a built-in key, a rolling pattern like "30d", or register one with Metrics::registerRange().',
            $key,
        ));
    }
}
