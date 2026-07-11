<?php

declare(strict_types=1);

namespace Syriable\Metrics\Exceptions;

final class UnknownBlueprintException extends MetricsException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf(
            'Unknown metric blueprint [%s]. Register it with BlueprintRegistry::register().',
            $key,
        ));
    }
}
