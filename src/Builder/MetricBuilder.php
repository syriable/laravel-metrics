<?php

declare(strict_types=1);

namespace Syriable\Metrics\Builder;

use Carbon\CarbonImmutable;
use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Syriable\Metrics\Contracts\ComparisonStrategy;
use Syriable\Metrics\Contracts\Range;
use Syriable\Metrics\Enums\Interval;
use Syriable\Metrics\Enums\MetricType;
use Syriable\Metrics\Exceptions\InvalidDefinitionException;
use Syriable\Metrics\Metrics;
use Syriable\Metrics\Results\MetricResult;
use Syriable\Metrics\Support\Period;

/**
 * The fluent definition of a metric. The builder only *describes* — every
 * terminal method (value / trend / partition / get) hands the description
 * to the engine, which does all the work. Nothing here touches the
 * database, the clock, or the request.
 */
final class MetricBuilder
{
    private ?string $key = null;

    /** @var array<string, DatasetBuilder> */
    private array $datasets = [];

    /** @var array<string, string> */
    private array $formulas = [];

    private Range|Period|string|null $range = null;

    private bool $allTime = false;

    private ?Interval $interval = null;

    private ?string $groupBy = null;

    private ?int $top = null;

    private string $othersKey = 'others';

    private ?string $timezone = null;

    private ?string $dateColumn = null;

    private ComparisonStrategy|string|null $comparison = null;

    private ?Closure $scope = null;

    private DateInterval|DateTimeInterface|int|null $cacheTtl = null;

    private bool $fresh = false;

    /** @var array<string, mixed> */
    private array $meta = [];

    private ?CarbonImmutable $now = null;

    private ?int $precision = null;

    private ?int $roundingMode = null;

    /**
     * @param  Closure(): Builder  $source
     */
    public function __construct(
        private readonly Metrics $manager,
        private readonly Closure $source,
    ) {}

    // ── Aggregates (configure the default dataset) ──────────────────────

    public function count(?string $column = null): self
    {
        $this->defaultDataset()->count($column);

        return $this;
    }

    public function countDistinct(string $column): self
    {
        $this->defaultDataset()->countDistinct($column);

        return $this;
    }

    public function sum(string $column): self
    {
        $this->defaultDataset()->sum($column);

        return $this;
    }

    public function average(string $column): self
    {
        $this->defaultDataset()->average($column);

        return $this;
    }

    public function avg(string $column): self
    {
        return $this->average($column);
    }

    public function min(string $column): self
    {
        $this->defaultDataset()->min($column);

        return $this;
    }

    public function max(string $column): self
    {
        $this->defaultDataset()->max($column);

        return $this;
    }

    /**
     * Use any registered aggregate by key.
     */
    public function aggregate(string $key, ?string $column = null): self
    {
        $this->defaultDataset()->aggregate($key, $column);

        return $this;
    }

    // ── Datasets & formulas ─────────────────────────────────────────────

    /**
     * Add a named dataset. One metric may combine several, each with its
     * own aggregate, constraints, or even source model.
     *
     * @param  Closure(DatasetBuilder): (DatasetBuilder|null|void)  $configure
     */
    public function dataset(string $name, Closure $configure): self
    {
        if (isset($this->datasets[$name]) || isset($this->formulas[$name])) {
            throw InvalidDefinitionException::duplicateDataset($name);
        }

        $dataset = new DatasetBuilder($name);
        $configure($dataset);

        $this->datasets[$name] = $dataset;

        return $this;
    }

    /**
     * Add a computed dataset, evaluated server-side over the other
     * datasets: ->formula('net', '[revenue] - [refunds]').
     */
    public function formula(string $name, string $expression): self
    {
        if (isset($this->datasets[$name]) || isset($this->formulas[$name])) {
            throw InvalidDefinitionException::duplicateDataset($name);
        }

        $this->formulas[$name] = $expression;

        return $this;
    }

    // ── Range & time ────────────────────────────────────────────────────

    /**
     * Constrain the metric to a range: a registered key ("today", "mtd"),
     * a rolling pattern ("30d", "12mo"), a Range object, or an explicit
     * Period.
     */
    public function range(Range|Period|string $range): self
    {
        $this->range = $range;
        $this->allTime = false;

        return $this;
    }

    /**
     * Constrain to an explicit window.
     */
    public function between(DateTimeInterface|string $start, DateTimeInterface|string $end, ?string $timezone = null): self
    {
        return $this->range(Period::between($start, $end, $timezone ?? $this->timezone));
    }

    /**
     * Remove the date constraint entirely.
     */
    public function allTime(): self
    {
        $this->allTime = true;
        $this->range = null;

        return $this;
    }

    /**
     * Bucket the metric by an interval, turning it into a trend.
     */
    public function per(Interval|string $interval): self
    {
        $this->interval = $interval instanceof Interval ? $interval : Interval::from($interval);

        return $this;
    }

    public function perMinute(): self
    {
        return $this->per(Interval::Minute);
    }

    public function perHour(): self
    {
        return $this->per(Interval::Hour);
    }

    public function perDay(): self
    {
        return $this->per(Interval::Day);
    }

    public function perWeek(): self
    {
        return $this->per(Interval::Week);
    }

    public function perMonth(): self
    {
        return $this->per(Interval::Month);
    }

    public function perQuarter(): self
    {
        return $this->per(Interval::Quarter);
    }

    public function perYear(): self
    {
        return $this->per(Interval::Year);
    }

    /**
     * Display timezone. Range math happens here; storage conversion is
     * configured separately (metrics.storage_timezone).
     */
    public function timezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * The date column used for range filtering and trend bucketing.
     * Defaults to the model's created_at column.
     */
    public function dateColumn(string $column): self
    {
        $this->dateColumn = $column;

        return $this;
    }

    // ── Partition ───────────────────────────────────────────────────────

    /**
     * Group results by a column, turning the metric into a partition.
     */
    public function groupBy(string $column): self
    {
        $this->groupBy = $column;

        return $this;
    }

    /**
     * Keep only the N largest groups and fold the rest into one bucket.
     */
    public function top(int $count, string $othersKey = 'others'): self
    {
        $this->top = $count;
        $this->othersKey = $othersKey;

        return $this;
    }

    // ── Comparison ──────────────────────────────────────────────────────

    /**
     * Compare against the immediately preceding period.
     */
    public function compareWithPrevious(): self
    {
        return $this->compareWith('previous_period');
    }

    public function compareWithPreviousWeek(): self
    {
        return $this->compareWith('previous_week');
    }

    public function compareWithPreviousMonth(): self
    {
        return $this->compareWith('previous_month');
    }

    public function compareWithPreviousQuarter(): self
    {
        return $this->compareWith('previous_quarter');
    }

    public function compareWithPreviousYear(): self
    {
        return $this->compareWith('previous_year');
    }

    /**
     * Compare using a registered strategy key or a custom strategy object.
     */
    public function compareWith(ComparisonStrategy|string $strategy): self
    {
        $this->comparison = $strategy;

        return $this;
    }

    // ── Query & execution options ───────────────────────────────────────

    /**
     * Constrain every dataset of this metric.
     *
     * @param  Closure(Builder): (Builder|null|void)  $scope
     */
    public function query(Closure $scope): self
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * Cache the computed data. TTL in seconds, or any Laravel TTL value.
     */
    public function cache(DateInterval|DateTimeInterface|int $ttl): self
    {
        $this->cacheTtl = $ttl;

        return $this;
    }

    /**
     * Bypass the cache for this execution.
     */
    public function fresh(): self
    {
        $this->fresh = true;

        return $this;
    }

    public function precision(int $digits, int $mode = PHP_ROUND_HALF_UP): self
    {
        $this->precision = $digits;
        $this->roundingMode = $mode;

        return $this;
    }

    /**
     * A stable identifier for the result payload (defaults to "metric").
     */
    public function as(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Attach free-form metadata, passed through to the payload untouched.
     *
     * @param  array<string, mixed>  $meta
     */
    public function meta(array $meta): self
    {
        $this->meta = [...$this->meta, ...$meta];

        return $this;
    }

    /**
     * Pin "now" — every range resolves against this instant. Deterministic
     * tests and backfills use this instead of freezing the global clock.
     */
    public function withNow(DateTimeInterface|string $now): self
    {
        $this->now = CarbonImmutable::parse($now);

        return $this;
    }

    // ── Terminal methods ────────────────────────────────────────────────

    public function value(): MetricResult
    {
        return $this->manager->engine()->run($this, MetricType::Value);
    }

    public function trend(): MetricResult
    {
        $this->interval ??= Interval::Day;

        return $this->manager->engine()->run($this, MetricType::Trend);
    }

    public function partition(): MetricResult
    {
        if ($this->groupBy === null) {
            throw InvalidDefinitionException::missingGroupBy();
        }

        return $this->manager->engine()->run($this, MetricType::Partition);
    }

    /**
     * Execute, inferring the type from the definition: groupBy ⇒ partition,
     * interval ⇒ trend, otherwise value.
     */
    public function get(): MetricResult
    {
        return match (true) {
            $this->groupBy !== null => $this->partition(),
            $this->interval !== null => $this->trend(),
            default => $this->value(),
        };
    }

    // ── Internal accessors (used by the engine) ─────────────────────────

    private function defaultDataset(): DatasetBuilder
    {
        return $this->datasets['default'] ??= new DatasetBuilder('default');
    }

    public function keyName(): string
    {
        return $this->key ?? 'metric';
    }

    /**
     * @return array<string, DatasetBuilder>
     */
    public function datasetBuilders(): array
    {
        if ($this->datasets === []) {
            $this->defaultDataset();
        }

        return $this->datasets;
    }

    /**
     * @return array<string, string>
     */
    public function formulaExpressions(): array
    {
        return $this->formulas;
    }

    public function rangeInput(): Range|Period|string|null
    {
        return $this->range;
    }

    public function isAllTime(): bool
    {
        return $this->allTime;
    }

    public function intervalUnit(): ?Interval
    {
        return $this->interval;
    }

    public function groupByColumn(): ?string
    {
        return $this->groupBy;
    }

    public function topCount(): ?int
    {
        return $this->top;
    }

    public function othersGroupKey(): string
    {
        return $this->othersKey;
    }

    public function timezoneName(): ?string
    {
        return $this->timezone;
    }

    public function dateColumnName(): ?string
    {
        return $this->dateColumn;
    }

    public function comparisonInput(): ComparisonStrategy|string|null
    {
        return $this->comparison;
    }

    public function metricScope(): ?Closure
    {
        return $this->scope;
    }

    public function cacheTtl(): DateInterval|DateTimeInterface|int|null
    {
        return $this->cacheTtl;
    }

    public function isFresh(): bool
    {
        return $this->fresh;
    }

    /**
     * @return array<string, mixed>
     */
    public function metaData(): array
    {
        return $this->meta;
    }

    public function nowOverride(): ?CarbonImmutable
    {
        return $this->now;
    }

    public function precisionDigits(): ?int
    {
        return $this->precision;
    }

    public function roundingMode(): ?int
    {
        return $this->roundingMode;
    }

    /**
     * @return Closure(): Builder
     */
    public function sourceResolver(): Closure
    {
        return $this->source;
    }
}
