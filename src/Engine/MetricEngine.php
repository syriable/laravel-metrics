<?php

declare(strict_types=1);

namespace Syriable\Metrics\Engine;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Syriable\Metrics\Builder\DatasetBuilder;
use Syriable\Metrics\Builder\MetricBuilder;
use Syriable\Metrics\Contracts\Aggregate;
use Syriable\Metrics\Contracts\ComparisonStrategy;
use Syriable\Metrics\Contracts\FormulaEvaluator;
use Syriable\Metrics\Enums\Interval;
use Syriable\Metrics\Enums\MetricType;
use Syriable\Metrics\Exceptions\InvalidDefinitionException;
use Syriable\Metrics\Metrics;
use Syriable\Metrics\Results\DatasetResult;
use Syriable\Metrics\Results\MetricResult;
use Syriable\Metrics\Results\PartitionGroup;
use Syriable\Metrics\Results\TrendPoint;
use Syriable\Metrics\Support\Comparison;
use Syriable\Metrics\Support\Period;
use Syriable\Metrics\Time\Timeline;

/**
 * Orchestrates one metric execution:
 *
 *   resolve context (now, timezones, period, comparison)
 *   → compute raw data per dataset (through the query engine,
 *     wrapped by the cache engine when caching is on)
 *   → evaluate formulas over the raw data
 *   → assemble the immutable MetricResult.
 *
 * Only plain arrays cross the cache boundary; only value objects leave
 * the engine.
 */
final readonly class MetricEngine
{
    public function __construct(
        private Metrics $manager,
        private QueryEngine $queries,
        private RangeEngine $ranges,
        private CacheEngine $cache,
    ) {}

    public function run(MetricBuilder $builder, MetricType $type): MetricResult
    {
        $timezone = $builder->timezoneName()
            ?? $this->manager->config('timezone')
            ?? config('app.timezone', 'UTC');

        $storageTimezone = $this->manager->config('storage_timezone')
            ?? config('app.timezone', 'UTC');

        $now = ($builder->nowOverride() ?? CarbonImmutable::now())->setTimezone($timezone);

        [$rangeKey, $period] = $this->ranges->resolve(
            $builder->rangeInput(),
            $builder->isAllTime(),
            $now,
            $type === MetricType::Partition && $builder->rangeInput() === null && ! $builder->isAllTime()
                ? 'all'
                : $this->manager->config('default_range'),
        );

        if ($type === MetricType::Trend && $period === null) {
            throw InvalidDefinitionException::trendRequiresPeriod();
        }

        $strategy = $this->resolveComparison($builder, $type);

        if ($strategy !== null && $period === null) {
            throw InvalidDefinitionException::comparisonRequiresPeriod();
        }

        $precision = $builder->precisionDigits() ?? (int) $this->manager->config('precision.digits', 2);
        $roundingMode = $builder->roundingMode() ?? (int) $this->manager->config('precision.mode', PHP_ROUND_HALF_UP);
        $interval = $builder->intervalUnit();

        $reference = $strategy?->referencePeriod($period);

        $compute = fn (): array => $this->compute(
            $builder, $type, $period, $reference, $interval,
            $now, $timezone, $storageTimezone, $precision, $roundingMode,
        );

        [$fromCache, $payload] = $this->throughCache(
            $builder, $type, $period, $reference, $interval, $timezone, $precision, $compute,
        );

        return $this->assemble(
            $builder, $type, $rangeKey, $period, $interval, $timezone,
            $strategy, $reference, $payload, $now, $fromCache, $precision, $roundingMode,
        );
    }

    // ── Computation (everything inside the cache boundary) ──────────────

    /**
     * @return array<string, array<string, mixed>> dataset name => raw data
     */
    private function compute(
        MetricBuilder $builder,
        MetricType $type,
        ?Period $period,
        ?Period $reference,
        ?Interval $interval,
        CarbonImmutable $now,
        string $timezone,
        string $storageTimezone,
        int $precision,
        int $roundingMode,
    ): array {
        $round = $this->rounder($precision, $roundingMode);
        $payload = [];

        foreach ($builder->datasetBuilders() as $dataset) {
            $aggregate = $this->aggregateFor($dataset);
            $query = $this->baseQuery($builder, $dataset);
            $dateColumn = $this->dateColumn($builder, $dataset, $query);

            $payload[$dataset->name()] = match ($type) {
                MetricType::Value => $this->computeValue(
                    $query, $aggregate, $dataset, $dateColumn, $period, $reference, $storageTimezone, $round,
                ),
                MetricType::Trend => $this->computeTrend(
                    $builder, $query, $aggregate, $dataset, $dateColumn, $period,
                    $reference, $interval, $now, $timezone, $storageTimezone, $round,
                ),
                MetricType::Partition => $this->computePartition(
                    $builder, $query, $aggregate, $dataset, $dateColumn, $period, $storageTimezone, $round,
                ),
            };
        }

        return $this->applyFormulas($builder, $type, $period, $interval, $payload, $round);
    }

    /**
     * @param  Closure(int|float|null): (int|float|null)  $round
     * @return array<string, mixed>
     */
    private function computeValue(
        Builder $query,
        Aggregate $aggregate,
        DatasetBuilder $dataset,
        string $dateColumn,
        ?Period $period,
        ?Period $reference,
        string $storageTimezone,
        Closure $round,
    ): array {
        [$current, $previous] = $this->queries->value(
            $query, $aggregate, $dataset->columnName(), $dateColumn, $period, $reference, $storageTimezone,
        );

        return [
            'value' => $round($current),
            'previous' => $reference === null ? null : $round($previous),
        ];
    }

    /**
     * @param  Closure(int|float|null): (int|float|null)  $round
     * @return array<string, mixed>
     */
    private function computeTrend(
        MetricBuilder $builder,
        Builder $query,
        Aggregate $aggregate,
        DatasetBuilder $dataset,
        string $dateColumn,
        Period $period,
        ?Period $reference,
        Interval $interval,
        CarbonImmutable $now,
        string $timezone,
        string $storageTimezone,
        Closure $round,
    ): array {
        // Query from the start of the first bucket so it is complete, not
        // clipped at the raw period start.
        $queryPeriod = new Period($interval->truncate($period->start), $period->end);

        $offsetMinutes = $now->setTimezone($timezone)->utcOffset()
            - $now->setTimezone($storageTimezone)->utcOffset();

        $rows = $this->queries->trend(
            $query, $aggregate, $dataset->columnName(), $dateColumn,
            $queryPeriod, $interval, $offsetMinutes, $storageTimezone,
        );

        // Gap-fill on canonical keys: the timeline dictates the buckets,
        // SQL rows land in them, anything else is discarded by construction.
        $series = [];
        $timeline = new Timeline($period, $interval, (int) $this->manager->config('max_buckets', 5000));

        foreach ($timeline->buckets() as $key => $start) {
            $series[$key] = $round(array_key_exists($key, $rows) ? $rows[$key] : $aggregate->emptyValue());
        }

        $total = $round($this->sumSeries($series));

        $comparison = null;

        if ($reference !== null) {
            [$current, $previous] = $this->queries->value(
                $query, $aggregate, $dataset->columnName(), $dateColumn,
                $period, $reference, $storageTimezone,
            );

            $comparison = ['value' => $round($current), 'previous' => $round($previous)];
        }

        return ['series' => $series, 'total' => $total, 'comparison' => $comparison];
    }

    /**
     * @param  Closure(int|float|null): (int|float|null)  $round
     * @return array<string, mixed>
     */
    private function computePartition(
        MetricBuilder $builder,
        Builder $query,
        Aggregate $aggregate,
        DatasetBuilder $dataset,
        string $dateColumn,
        ?Period $period,
        string $storageTimezone,
        Closure $round,
    ): array {
        $rows = $this->queries->partition(
            $query, $aggregate, $dataset->columnName(), (string) $builder->groupByColumn(),
            $dateColumn, $period, $storageTimezone,
        );

        if ($builder->topCount() !== null && count($rows) > $builder->topCount()) {
            $kept = array_slice($rows, 0, $builder->topCount());
            $others = array_slice($rows, $builder->topCount());

            $kept[] = [
                'key' => $builder->othersGroupKey(),
                'value' => $this->sumSeries(array_column($others, 'value', 'key')),
            ];

            $rows = $kept;
        }

        $groups = [];

        foreach ($rows as $row) {
            $groups[] = ['key' => $row['key'], 'value' => $round($row['value'])];
        }

        return ['groups' => $groups, 'total' => $round($this->sumSeries(array_column($groups, 'value')))];
    }

    /**
     * Evaluate formula datasets over the computed raw data.
     *
     * @param  array<string, array<string, mixed>>  $payload
     * @param  Closure(int|float|null): (int|float|null)  $round
     * @return array<string, array<string, mixed>>
     */
    private function applyFormulas(
        MetricBuilder $builder,
        MetricType $type,
        ?Period $period,
        ?Interval $interval,
        array $payload,
        Closure $round,
    ): array {
        $formulas = $builder->formulaExpressions();

        if ($formulas === []) {
            return $payload;
        }

        $evaluator = $this->manager->formulaEvaluator();

        foreach ($formulas as $name => $expression) {
            $payload[$name] = match ($type) {
                MetricType::Value => $this->formulaValue($evaluator, $expression, $payload, $round),
                MetricType::Trend => $this->formulaTrend($evaluator, $expression, $payload, $round),
                MetricType::Partition => $this->formulaPartition($evaluator, $expression, $payload, $round),
            };
        }

        return $payload;
    }

    /**
     * @param  array<string, array<string, mixed>>  $payload
     * @param  Closure(int|float|null): (int|float|null)  $round
     * @return array<string, mixed>
     */
    private function formulaValue(FormulaEvaluator $evaluator, string $expression, array $payload, Closure $round): array
    {
        $current = $previous = [];
        $comparing = false;

        foreach ($payload as $name => $data) {
            if (! array_key_exists('value', $data)) {
                continue;
            }

            $current[$name] = $data['value'];
            $previous[$name] = $data['previous'];
            $comparing = $comparing || $data['previous'] !== null;
        }

        return [
            'value' => $round($evaluator->evaluate($expression, $current)),
            'previous' => $comparing ? $round($evaluator->evaluate($expression, $previous)) : null,
            'formula' => true,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $payload
     * @param  Closure(int|float|null): (int|float|null)  $round
     * @return array<string, mixed>
     */
    private function formulaTrend(FormulaEvaluator $evaluator, string $expression, array $payload, Closure $round): array
    {
        $seriesByDataset = [];
        $totals = [];
        $comparisons = ['value' => [], 'previous' => []];
        $comparing = false;

        foreach ($payload as $name => $data) {
            if (! array_key_exists('series', $data)) {
                continue;
            }

            $seriesByDataset[$name] = $data['series'];
            $totals[$name] = $data['total'];

            if (($data['comparison'] ?? null) !== null) {
                $comparing = true;
                $comparisons['value'][$name] = $data['comparison']['value'];
                $comparisons['previous'][$name] = $data['comparison']['previous'];
            }
        }

        $keys = $seriesByDataset === [] ? [] : array_keys(reset($seriesByDataset));
        $series = [];

        foreach ($keys as $key) {
            $variables = [];

            foreach ($seriesByDataset as $name => $datasetSeries) {
                $variables[$name] = $datasetSeries[$key] ?? null;
            }

            $series[$key] = $round($evaluator->evaluate($expression, $variables));
        }

        return [
            'series' => $series,
            'total' => $round($evaluator->evaluate($expression, $totals)),
            'comparison' => $comparing ? [
                'value' => $round($evaluator->evaluate($expression, $comparisons['value'])),
                'previous' => $round($evaluator->evaluate($expression, $comparisons['previous'])),
            ] : null,
            'formula' => true,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $payload
     * @param  Closure(int|float|null): (int|float|null)  $round
     * @return array<string, mixed>
     */
    private function formulaPartition(FormulaEvaluator $evaluator, string $expression, array $payload, Closure $round): array
    {
        $groupsByDataset = [];
        $totals = [];
        $keys = [];

        foreach ($payload as $name => $data) {
            if (! array_key_exists('groups', $data)) {
                continue;
            }

            $indexed = [];

            foreach ($data['groups'] as $group) {
                $indexed[$this->groupIndex($group['key'])] = $group;
                $keys[$this->groupIndex($group['key'])] = $group['key'];
            }

            $groupsByDataset[$name] = $indexed;
            $totals[$name] = $data['total'];
        }

        $groups = [];

        foreach ($keys as $index => $key) {
            $variables = [];

            foreach ($groupsByDataset as $name => $indexed) {
                $variables[$name] = $indexed[$index]['value'] ?? null;
            }

            $groups[] = ['key' => $key, 'value' => $round($evaluator->evaluate($expression, $variables))];
        }

        return [
            'groups' => $groups,
            'total' => $round($evaluator->evaluate($expression, $totals)),
            'formula' => true,
        ];
    }

    // ── Cache boundary ───────────────────────────────────────────────────

    /**
     * @param  Closure(): array<string, array<string, mixed>>  $compute
     * @return array{0: bool, 1: array<string, array<string, mixed>>}
     */
    private function throughCache(
        MetricBuilder $builder,
        MetricType $type,
        ?Period $period,
        ?Period $reference,
        ?Interval $interval,
        string $timezone,
        int $precision,
        Closure $compute,
    ): array {
        $ttl = $builder->cacheTtl() ?? $this->manager->config('cache.ttl');

        if ($ttl === null || $builder->isFresh()) {
            return [false, $compute()];
        }

        return $this->cache->remember($this->cacheKey(
            $builder, $type, $period, $reference, $interval, $timezone, $precision,
        ), $ttl, $compute);
    }

    /**
     * Content-derived cache key: hashes the exact SQL + bindings of every
     * dataset plus every execution input that changes the numbers.
     */
    public function cacheKey(
        MetricBuilder $builder,
        MetricType $type,
        ?Period $period,
        ?Period $reference,
        ?Interval $interval,
        string $timezone,
        int $precision,
    ): string {
        $fingerprints = [];

        foreach ($builder->datasetBuilders() as $dataset) {
            $query = $this->baseQuery($builder, $dataset);

            $fingerprints[$dataset->name()] = [
                $dataset->aggregateKey(),
                $dataset->columnName(),
                $this->dateColumn($builder, $dataset, $query),
                $this->queries->fingerprint($query),
            ];
        }

        return $this->cache->key([
            $type->value,
            $period?->toArray(),
            $reference?->toArray(),
            $interval?->value,
            $timezone,
            $builder->groupByColumn(),
            $builder->topCount(),
            $builder->formulaExpressions(),
            $precision,
            $fingerprints,
        ]);
    }

    // ── Result assembly (outside the cache boundary) ─────────────────────

    /**
     * @param  array<string, array<string, mixed>>  $payload
     */
    private function assemble(
        MetricBuilder $builder,
        MetricType $type,
        ?string $rangeKey,
        ?Period $period,
        ?Interval $interval,
        string $timezone,
        ?ComparisonStrategy $strategy,
        ?Period $reference,
        array $payload,
        CarbonImmutable $now,
        bool $fromCache,
        int $precision,
        int $roundingMode,
    ): MetricResult {
        $datasets = [];

        foreach ($payload as $name => $data) {
            $isFormula = (bool) ($data['formula'] ?? false);

            $datasets[] = match ($type) {
                MetricType::Value => new DatasetResult(
                    name: $name,
                    value: $data['value'],
                    comparison: $strategy !== null && $reference !== null ? new Comparison(
                        $strategy->key(), $reference, $this->toFloat($data['value']), $this->toFloat($data['previous']),
                        $precision, $roundingMode,
                    ) : null,
                    formula: $isFormula,
                ),
                MetricType::Trend => new DatasetResult(
                    name: $name,
                    value: $data['total'],
                    points: $this->points($data['series'], $period, $interval),
                    comparison: $strategy !== null && $reference !== null && $data['comparison'] !== null
                        ? new Comparison(
                            $strategy->key(), $reference,
                            $this->toFloat($data['comparison']['value']), $this->toFloat($data['comparison']['previous']),
                            $precision, $roundingMode,
                        )
                        : null,
                    formula: $isFormula,
                ),
                MetricType::Partition => new DatasetResult(
                    name: $name,
                    value: $data['total'],
                    groups: $this->partitionGroups($data['groups'], $data['total'], $precision, $roundingMode),
                    formula: $isFormula,
                ),
            };
        }

        return new MetricResult(
            key: $builder->keyName(),
            type: $type,
            rangeKey: $rangeKey,
            period: $period,
            interval: $interval,
            timezone: $timezone,
            datasets: $datasets,
            generatedAt: $now,
            fromCache: $fromCache,
            meta: $builder->metaData(),
            serializer: $this->manager->serializer(),
        );
    }

    /**
     * @param  array<string, int|float|null>  $series
     * @return list<TrendPoint>
     */
    private function points(array $series, Period $period, Interval $interval): array
    {
        $points = [];
        $timeline = new Timeline($period, $interval, (int) $this->manager->config('max_buckets', 5000));

        foreach ($timeline->buckets() as $key => $start) {
            $points[] = new TrendPoint($key, $interval->label($start), $start, $series[$key] ?? null);
        }

        return $points;
    }

    /**
     * @param  list<array{key: mixed, value: int|float|null}>  $groups
     * @return list<PartitionGroup>
     */
    private function partitionGroups(array $groups, int|float|null $total, int $precision, int $roundingMode): array
    {
        $result = [];

        foreach ($groups as $group) {
            $percentage = ($total !== null && $total > 0 && $group['value'] !== null)
                ? round($group['value'] / $total * 100, $precision, $roundingMode)
                : null;

            $result[] = new PartitionGroup($group['key'], $group['value'], $percentage);
        }

        return $result;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function resolveComparison(MetricBuilder $builder, MetricType $type): ?ComparisonStrategy
    {
        $input = $builder->comparisonInput();

        if ($input === null || $type === MetricType::Partition) {
            return null;
        }

        return is_string($input) ? $this->manager->comparison($input) : $input;
    }

    private function aggregateFor(DatasetBuilder $dataset): Aggregate
    {
        $aggregate = $this->manager->aggregate($dataset->aggregateKey());

        if ($aggregate->requiresColumn() && $dataset->columnName() === null) {
            throw InvalidDefinitionException::missingColumn($aggregate->key());
        }

        return $aggregate;
    }

    private function baseQuery(MetricBuilder $builder, DatasetBuilder $dataset): Builder
    {
        $source = $dataset->source();

        $query = $source === null
            ? ($builder->sourceResolver())()
            : Metrics::resolveSource($source)();

        if ($builder->metricScope() !== null) {
            $query = ($builder->metricScope())($query) ?? $query;
        }

        if ($dataset->scope() !== null) {
            $query = ($dataset->scope())($query) ?? $query;
        }

        return $query;
    }

    private function dateColumn(MetricBuilder $builder, DatasetBuilder $dataset, Builder $query): string
    {
        return $dataset->dateColumnName()
            ?? $builder->dateColumnName()
            ?? $query->getModel()->getQualifiedCreatedAtColumn()
            ?? 'created_at';
    }

    /**
     * @return Closure(int|float|null): (int|float|null)
     */
    private function rounder(int $precision, int $roundingMode): Closure
    {
        return static function (int|float|null $value) use ($precision, $roundingMode): int|float|null {
            if ($value === null) {
                return null;
            }

            $rounded = round((float) $value, $precision, $roundingMode);

            // Keep integers as integers so JSON payloads stay clean.
            return $rounded === (float) (int) $rounded ? (int) $rounded : $rounded;
        };
    }

    /**
     * Null-safe sum: all-null stays null, otherwise nulls are ignored.
     *
     * @param  array<array-key, int|float|null>  $values
     */
    private function sumSeries(array $values): int|float|null
    {
        $sum = null;

        foreach ($values as $value) {
            if ($value !== null) {
                $sum = ($sum ?? 0) + $value;
            }
        }

        return $sum;
    }

    private function toFloat(int|float|null $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    private function groupIndex(mixed $key): string
    {
        return is_bool($key) ? ($key ? '__true__' : '__false__') : (string) ($key ?? '__null__');
    }
}
