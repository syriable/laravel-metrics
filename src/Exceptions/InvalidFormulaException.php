<?php

declare(strict_types=1);

namespace Syriable\Metrics\Exceptions;

final class InvalidFormulaException extends MetricsException
{
    public static function unexpectedToken(string $expression, string $token): self
    {
        return new self(sprintf('Invalid formula [%s]: unexpected token "%s".', $expression, $token));
    }

    public static function unknownVariable(string $expression, string $variable): self
    {
        return new self(sprintf('Invalid formula [%s]: unknown dataset "%s".', $expression, $variable));
    }

    public static function malformed(string $expression): self
    {
        return new self(sprintf('Invalid formula [%s]: malformed expression.', $expression));
    }
}
