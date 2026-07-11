<?php

declare(strict_types=1);

namespace Syriable\Metrics\Contracts;

/**
 * A metric scaffold `make:metric` can generate.
 *
 * The command and generator know nothing about specific metric shapes —
 * the default blank metric, and future value/trend/partition/table/heatmap
 * variants, are each a small self-contained blueprint registered with the
 * generator's blueprint registry. Adding one is registration, the same
 * open-vocabulary pattern the runtime engine uses for aggregates, ranges
 * and comparisons (see docs/decisions/004-registries-over-inheritance.md).
 */
interface MetricBlueprint
{
    /**
     * The registry key, e.g. "default", "trend", "value".
     */
    public function key(): string;

    /**
     * The `--{option}` command-line flag that selects this blueprint, or
     * null for the default blueprint (used when no shape flag is given).
     */
    public function option(): ?string;

    /**
     * One-line description shown next to the option in `make:metric --help`.
     */
    public function description(): string;

    /**
     * Absolute path to this blueprint's bundled stub file.
     */
    public function stubPath(): string;
}
