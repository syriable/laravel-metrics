<?php

declare(strict_types=1);

namespace Syriable\Metrics\Exceptions;

final class UnsupportedDriverException extends MetricsException
{
    public static function forDriver(string $driver): self
    {
        return new self(sprintf(
            'No date dialect registered for database driver [%s]. Register one with Metrics::registerDialect().',
            $driver,
        ));
    }
}
