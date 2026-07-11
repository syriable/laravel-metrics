<?php

declare(strict_types=1);

namespace Syriable\Metrics\Exceptions;

final class InvalidMetricNameException extends MetricsException
{
    public static function empty(): self
    {
        return new self('The metric name must not be empty.');
    }

    public static function forName(string $name): self
    {
        return new self(sprintf(
            'The name [%s] is not a valid class name. Use letters, numbers and underscores, '.
            'optionally separated by "/" or "\\" for a subdirectory (e.g. "Sales/Revenue").',
            $name,
        ));
    }

    public static function reserved(string $name): self
    {
        return new self(sprintf(
            'The name [%s] is reserved by PHP and cannot be used as a class name.',
            $name,
        ));
    }
}
