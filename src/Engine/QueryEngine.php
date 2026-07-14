<?php

declare(strict_types=1);

namespace Syriable\Metrics\Engine;

use Illuminate\Database\Eloquent\Builder;
use Syriable\Metrics\Contracts\Aggregate;
use Syriable\Metrics\Enums\Interval;
use Syriable\Metrics\Metrics;
use Syriable\Metrics\Support\Period;

/**
 * The only component that builds and executes SQL.
 *
 * Design rules:
 * - every aggregation happens database-side; rows are never hydrated
 *   (queries run on the base query builder, not Eloquent models);
 * - raw fragments are assembled exclusively from grammar-wrapped
 *   identifiers, registered aggregate/dialect output, and "?" placeholders
 *   with bindings — no user input is ever interpolated;
 * - a value + comparison resolves in ONE query via conditional
 *   aggregation (CASE inside the aggregate), not two scans.
 */
final readonly class QueryEngine
{
    public function __construct(private Metrics $manager) {}

    /**
     * Aggregate over an optional period, optionally computing a reference
     * period in the same query.
     *
     * @return array{0: int|float|null, 1: int|float|null} [current, previous]
     */
    public function value(
        Builder $query,
        Aggregate $aggregate,
        ?string $column,
        string $dateColumn,
        ?Period $period,
        ?Period $reference,
        string $storageTimezone,
    ): array {
        // Work on a clone: toBase() exposes the caller's underlying
        // builder, and one metric may run several queries from one source.
        $query = clone $query;
        $grammar = $query->getQuery()->getGrammar();
        $inner = $this->inner($grammar->wrap(...), $aggregate, $column);

        if ($period === null) {
            $row = $query->toBase()
                ->selectRaw($aggregate->expression($inner).' as current_value')
                ->first();

            return [$row->current_value ?? $aggregate->emptyValue(), null];
        }

        $wrappedDate = $grammar->wrap($dateColumn);
        [$currentStart, $currentEnd] = $this->bounds($period, $storageTimezone);

        if ($reference === null) {
            $row = $query->toBase()
                ->selectRaw($aggregate->expression($inner).' as current_value')
                ->whereBetween($dateColumn, [$currentStart, $currentEnd])
                ->first();

            return [$row->current_value ?? $aggregate->emptyValue(), null];
        }

        [$previousStart, $previousEnd] = $this->bounds($reference, $storageTimezone);

        // One scan covers both windows: the outer WHERE keeps the range
        // index-friendly, the CASE expressions split rows per window.
        // Column-less aggregates count matches via "then 1" (non-null).
        $caseInner = $column !== null ? $grammar->wrap($column) : '1';
        $case = "case when {$wrappedDate} between ? and ? then {$caseInner} end";

        $row = $query->toBase()
            ->selectRaw(
                $aggregate->expression($case).' as current_value, '
                .$aggregate->expression($case).' as previous_value',
                [$currentStart, $currentEnd, $previousStart, $previousEnd],
            )
            ->whereBetween($dateColumn, [
                min($currentStart, $previousStart),
                max($currentEnd, $previousEnd),
            ])
            ->first();

        return [
            $row->current_value ?? $aggregate->emptyValue(),
            $row->previous_value ?? $aggregate->emptyValue(),
        ];
    }

    /**
     * One grouped query producing canonical bucket key => value.
     *
     * @return array<string, int|float|null>
     */
    public function trend(
        Builder $query,
        Aggregate $aggregate,
        ?string $column,
        string $dateColumn,
        Period $period,
        Interval $interval,
        int $offsetMinutes,
        string $storageTimezone,
    ): array {
        // Work on a clone: toBase() exposes the caller's underlying
        // builder, and one metric may run several queries from one source.
        $query = clone $query;
        $grammar = $query->getQuery()->getGrammar();
        $inner = $this->inner($grammar->wrap(...), $aggregate, $column);

        $bucket = $this->manager
            ->dialect($query->getConnection()->getDriverName()) // @phpstan-ignore-line
            ->bucketExpression($grammar->wrap($dateColumn), $interval, $offsetMinutes);

        [$start, $end] = $this->bounds($period, $storageTimezone);

        $rows = $query->toBase()
            ->selectRaw("{$bucket} as bucket_key, ".$aggregate->expression($inner).' as aggregate_value')
            ->whereBetween($dateColumn, [$start, $end])
            ->groupByRaw($bucket)
            ->get();

        $series = [];

        foreach ($rows as $row) {
            $series[(string) $row->bucket_key] = $row->aggregate_value;
        }

        return $series;
    }

    /**
     * One grouped query producing group key => value.
     *
     * @return array<int, array{key: mixed, value: int|float|null}>
     */
    public function partition(
        Builder $query,
        Aggregate $aggregate,
        ?string $column,
        string $groupBy,
        string $dateColumn,
        ?Period $period,
        string $storageTimezone,
    ): array {
        // Work on a clone: toBase() exposes the caller's underlying
        // builder, and one metric may run several queries from one source.
        $query = clone $query;
        $grammar = $query->getQuery()->getGrammar();
        $inner = $this->inner($grammar->wrap(...), $aggregate, $column);

        $base = $query->toBase()
            ->selectRaw($grammar->wrap($groupBy).' as group_key, '.$aggregate->expression($inner).' as aggregate_value')
            ->groupBy($groupBy)
            ->orderByDesc('aggregate_value');

        if ($period !== null) {
            $base->whereBetween($dateColumn, $this->bounds($period, $storageTimezone));
        }

        return $base->get()
            ->map(static fn (object $row): array => ['key' => $row->group_key, 'value' => $row->aggregate_value])
            ->all();
    }

    /**
     * The SQL + bindings a dataset query would run — used as the cache
     * fingerprint, so any change to scopes or sources is a cache miss.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    public function fingerprint(Builder $query): array
    {
        // Work on a clone: toBase() exposes the caller's underlying
        // builder, and one metric may run several queries from one source.
        $query = clone $query;
        $base = $query->toBase();

        return [$base->toSql(), $base->getBindings()];
    }

    /**
     * The inner expression handed to the aggregate: the wrapped column,
     * or "*" for column-less aggregates like count.
     *
     * @param  callable(string): string  $wrap
     */
    private function inner(callable $wrap, Aggregate $aggregate, ?string $column): string
    {
        if ($column !== null) {
            return $wrap($column);
        }

        return '*';
    }

    /**
     * Period bounds as datetime strings in the storage timezone, ready to
     * bind. Range math happens in the display timezone; rows are filtered
     * in the timezone they are stored in.
     *
     * @return array{0: string, 1: string}
     */
    private function bounds(Period $period, string $storageTimezone): array
    {
        return [
            $period->start->setTimezone($storageTimezone)->format('Y-m-d H:i:s'),
            $period->end->setTimezone($storageTimezone)->format('Y-m-d H:i:s'),
        ];
    }
}
