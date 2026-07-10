<?php

declare(strict_types=1);

namespace Syriable\Metrics\Contracts;

/**
 * Evaluates a formula expression over named dataset values.
 *
 * The default implementation is a small arithmetic parser (no eval, no
 * callbacks into the database). Replace it via Metrics::useFormulaEvaluator()
 * to support custom functions or a full expression language.
 */
interface FormulaEvaluator
{
    /**
     * @param  array<string, int|float|null>  $variables  dataset name => value
     */
    public function evaluate(string $expression, array $variables): ?float;
}
