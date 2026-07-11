<?php

declare(strict_types=1);

namespace Syriable\Metrics\Exceptions;

final class StubNotFoundException extends MetricsException
{
    public static function atPath(string $path): self
    {
        return new self(sprintf(
            'The metric stub [%s] does not exist. Publish it with '.
            'php artisan vendor:publish --tag="laravel-metrics-stubs", or check the '.
            'metrics.generator.stub configuration value.',
            $path,
        ));
    }
}
