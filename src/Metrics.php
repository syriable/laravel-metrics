<?php

declare(strict_types=1);

namespace Syriable\Metrics;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Syriable\Metrics\Aggregates\Average;
use Syriable\Metrics\Aggregates\Count;
use Syriable\Metrics\Aggregates\CountDistinct;
use Syriable\Metrics\Aggregates\Maximum;
use Syriable\Metrics\Aggregates\Minimum;
use Syriable\Metrics\Aggregates\Sum;
use Syriable\Metrics\Builder\MetricBuilder;
use Syriable\Metrics\Comparisons\PreviousPeriod;
use Syriable\Metrics\Comparisons\ShiftedPeriod;
use Syriable\Metrics\Contracts\Aggregate;
use Syriable\Metrics\Contracts\ComparisonStrategy;
use Syriable\Metrics\Contracts\DateDialect;
use Syriable\Metrics\Contracts\FormulaEvaluator;
use Syriable\Metrics\Contracts\Range;
use Syriable\Metrics\Contracts\Serializer;
use Syriable\Metrics\Engine\CacheEngine;
use Syriable\Metrics\Engine\MetricEngine;
use Syriable\Metrics\Engine\QueryEngine;
use Syriable\Metrics\Engine\RangeEngine;
use Syriable\Metrics\Enums\Interval;
use Syriable\Metrics\Exceptions\InvalidDefinitionException;
use Syriable\Metrics\Exceptions\UnknownAggregateException;
use Syriable\Metrics\Exceptions\UnknownComparisonException;
use Syriable\Metrics\Exceptions\UnknownRangeException;
use Syriable\Metrics\Exceptions\UnsupportedDriverException;
use Syriable\Metrics\Formulas\DefaultFormulaEvaluator;
use Syriable\Metrics\Ranges\CallbackRange;
use Syriable\Metrics\Results\MetricResult;
use Syriable\Metrics\Serializers\ArraySerializer;
use Syriable\Metrics\Support\Period;
use Syriable\Metrics\Time\Dialects\MySqlDialect;
use Syriable\Metrics\Time\Dialects\PostgresDialect;
use Syriable\Metrics\Time\Dialects\SqliteDialect;
use Syriable\Metrics\Time\Dialects\SqlServerDialect;

/**
 * The engine's front door and its registries.
 *
 * Every vocabulary the engine consumes — aggregates, date dialects, named
 * ranges, comparison strategies, the serializer, the formula evaluator —
 * lives here and is replaceable at runtime, so extending the engine never
 * means modifying it.
 */
class Metrics
{
    /** @var array<string, Aggregate> */
    private array $aggregates = [];

    /** @var array<string, DateDialect> */
    private array $dialects = [];

    /** @var array<string, Range> */
    private array $ranges = [];

    /** @var array<string, ComparisonStrategy> */
    private array $comparisons = [];

    /** @var array<string, Metric> */
    private array $metrics = [];

    private Serializer $serializer;

    private FormulaEvaluator $formulaEvaluator;

    private ?MetricEngine $engine = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly CacheFactory $cacheFactory,
        private array $config = [],
    ) {
        $this->serializer = new ArraySerializer;
        $this->formulaEvaluator = new DefaultFormulaEvaluator;

        $this->registerDefaultAggregates();
        $this->registerDefaultDialects();
        $this->registerDefaultRanges();
        $this->registerDefaultComparisons();
    }

    // ── Entry points ─────────────────────────────────────────────────────

    /**
     * Start defining a metric over the given source.
     *
     * @param  Builder|Model|class-string<Model>|Closure(): Builder  $source
     */
    public function query(Builder|Model|string|Closure $source): MetricBuilder
    {
        return new MetricBuilder($this, self::resolveSource($source));
    }

    /**
     * Register named metric classes for lookup-based execution (the way an
     * API endpoint exposes metrics by key).
     *
     * @param  class-string<Metric>|Metric  ...$metrics
     */
    public function register(string|Metric ...$metrics): static
    {
        foreach ($metrics as $metric) {
            $instance = is_string($metric) ? new $metric : $metric;
            $this->metrics[$instance->key()] = $instance;
        }

        return $this;
    }

    /**
     * Execute a registered metric, optionally overriding execution context
     * (typically straight from validated request input).
     *
     * @param  array{range?: string, interval?: string, timezone?: string, compare?: string, fresh?: bool}  $context
     */
    public function run(string $key, array $context = []): MetricResult
    {
        $metric = $this->metrics[$key] ?? throw InvalidDefinitionException::unknownMetric($key);

        $builder = $metric->query()->as($key);

        if (isset($context['range'])) {
            $builder->range($context['range']);
        }

        if (isset($context['interval'])) {
            $builder->per(Interval::from($context['interval']));
        }

        if (isset($context['timezone'])) {
            $builder->timezone($context['timezone']);
        }

        if (isset($context['compare'])) {
            $builder->compareWith($context['compare']);
        }

        if ($context['fresh'] ?? false) {
            $builder->fresh();
        }

        return $builder->get();
    }

    /**
     * The registered metrics, key => instance.
     *
     * @return array<string, Metric>
     */
    public function registered(): array
    {
        return $this->metrics;
    }

    // ── Extension registries ─────────────────────────────────────────────

    public function registerAggregate(Aggregate $aggregate): static
    {
        $this->aggregates[$aggregate->key()] = $aggregate;

        return $this;
    }

    public function registerDialect(DateDialect $dialect): static
    {
        foreach ($dialect->drivers() as $driver) {
            $this->dialects[$driver] = $dialect;
        }

        return $this;
    }

    public function registerRange(Range $range): static
    {
        $this->ranges[$range->key()] = $range;

        return $this;
    }

    public function registerComparison(ComparisonStrategy $strategy): static
    {
        $this->comparisons[$strategy->key()] = $strategy;

        return $this;
    }

    public function useSerializer(Serializer $serializer): static
    {
        $this->serializer = $serializer;

        return $this;
    }

    public function useFormulaEvaluator(FormulaEvaluator $evaluator): static
    {
        $this->formulaEvaluator = $evaluator;

        return $this;
    }

    // ── Registry lookups ─────────────────────────────────────────────────

    public function aggregate(string $key): Aggregate
    {
        return $this->aggregates[$key] ?? throw UnknownAggregateException::forKey($key);
    }

    public function dialect(string $driver): DateDialect
    {
        return $this->dialects[$driver] ?? throw UnsupportedDriverException::forDriver($driver);
    }

    /**
     * Look up a named range; rolling patterns ("30d", "12mo", "4w", "24h",
     * "90m", "2y") resolve dynamically without registration.
     */
    public function range(string $key): Range
    {
        if (isset($this->ranges[$key])) {
            return $this->ranges[$key];
        }

        if (preg_match('/^(\d+)(mo|m|h|d|w|q|y)$/', $key, $matches) === 1) {
            return $this->rollingRange($key, (int) $matches[1], $matches[2]);
        }

        throw UnknownRangeException::forKey($key);
    }

    public function comparison(string $key): ComparisonStrategy
    {
        return $this->comparisons[$key] ?? throw UnknownComparisonException::forKey($key);
    }

    public function serializer(): Serializer
    {
        return $this->serializer;
    }

    public function formulaEvaluator(): FormulaEvaluator
    {
        return $this->formulaEvaluator;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    public function engine(): MetricEngine
    {
        return $this->engine ??= new MetricEngine(
            $this,
            new QueryEngine($this),
            new RangeEngine($this),
            new CacheEngine($this->cacheFactory, [
                'store' => $this->config('cache.store'),
                'prefix' => (string) $this->config('cache.prefix', 'metrics'),
            ]),
        );
    }

    /**
     * Normalize any accepted source into a fresh-builder factory.
     *
     * @param  Builder|Model|class-string<Model>|Closure(): Builder  $source
     * @return Closure(): Builder
     */
    public static function resolveSource(Builder|Model|string|Closure $source): Closure
    {
        return match (true) {
            $source instanceof Closure => $source,
            $source instanceof Builder => static fn (): Builder => clone $source,
            $source instanceof Model => static fn (): Builder => $source->newQuery(),
            default => static fn (): Builder => $source::query(),
        };
    }

    // ── Built-in vocabulary ──────────────────────────────────────────────

    private function registerDefaultAggregates(): void
    {
        $this->registerAggregate(new Count);
        $this->registerAggregate(new CountDistinct);
        $this->registerAggregate(new Sum);
        $this->registerAggregate(new Average);
        $this->registerAggregate(new Minimum);
        $this->registerAggregate(new Maximum);
    }

    private function registerDefaultDialects(): void
    {
        $this->registerDialect(new SqliteDialect);
        $this->registerDialect(new MySqlDialect);
        $this->registerDialect(new PostgresDialect);
        $this->registerDialect(new SqlServerDialect);
    }

    private function registerDefaultRanges(): void
    {
        $calendar = [
            ['today', 'Today', static fn (CarbonImmutable $now) => new Period($now->startOfDay(), $now, Interval::Day)],
            ['yesterday', 'Yesterday', static fn (CarbonImmutable $now) => new Period($now->subDay()->startOfDay(), $now->subDay()->endOfDay(), Interval::Day)],
            ['wtd', 'Week to date', static fn (CarbonImmutable $now) => new Period($now->startOfWeek(), $now, Interval::Week)],
            ['last_week', 'Last week', static fn (CarbonImmutable $now) => new Period($now->subWeek()->startOfWeek(), $now->subWeek()->endOfWeek(), Interval::Week)],
            ['mtd', 'Month to date', static fn (CarbonImmutable $now) => new Period($now->startOfMonth(), $now, Interval::Month)],
            ['last_month', 'Last month', static fn (CarbonImmutable $now) => new Period($now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth(), Interval::Month)],
            ['qtd', 'Quarter to date', static fn (CarbonImmutable $now) => new Period($now->startOfQuarter(), $now, Interval::Quarter)],
            ['last_quarter', 'Last quarter', static fn (CarbonImmutable $now) => new Period($now->subQuarterNoOverflow()->startOfQuarter(), $now->subQuarterNoOverflow()->endOfQuarter(), Interval::Quarter)],
            ['ytd', 'Year to date', static fn (CarbonImmutable $now) => new Period($now->startOfYear(), $now, Interval::Year)],
            ['last_year', 'Last year', static fn (CarbonImmutable $now) => new Period($now->subYear()->startOfYear(), $now->subYear()->endOfYear(), Interval::Year)],
        ];

        foreach ($calendar as [$key, $label, $resolver]) {
            $this->registerRange(new CallbackRange($key, $label, $resolver));
        }

        $this->registerRange(new CallbackRange('all', 'All time', static fn (CarbonImmutable $now): ?Period => null));
    }

    private function rollingRange(string $key, int $amount, string $unit): Range
    {
        [$label, $resolver] = match ($unit) {
            'm' => ["Last {$amount} minutes", static fn (CarbonImmutable $now) => new Period($now->subMinutes($amount), $now)],
            'h' => ["Last {$amount} hours", static fn (CarbonImmutable $now) => new Period($now->subHours($amount), $now)],
            'd' => ["Last {$amount} days", static fn (CarbonImmutable $now) => new Period($now->subDays($amount), $now)],
            'w' => ["Last {$amount} weeks", static fn (CarbonImmutable $now) => new Period($now->subWeeks($amount), $now)],
            'mo' => ["Last {$amount} months", static fn (CarbonImmutable $now) => new Period($now->subMonthsNoOverflow($amount), $now)],
            'q' => ["Last {$amount} quarters", static fn (CarbonImmutable $now) => new Period($now->subMonthsNoOverflow(3 * $amount), $now)],
            default => ["Last {$amount} years", static fn (CarbonImmutable $now) => new Period($now->subYears($amount), $now)],
        };

        return new CallbackRange($key, $label, $resolver);
    }

    private function registerDefaultComparisons(): void
    {
        $this->registerComparison(new PreviousPeriod);
        $this->registerComparison(new ShiftedPeriod('previous_week', Interval::Week));
        $this->registerComparison(new ShiftedPeriod('previous_month', Interval::Month));
        $this->registerComparison(new ShiftedPeriod('previous_quarter', Interval::Quarter));
        $this->registerComparison(new ShiftedPeriod('previous_year', Interval::Year));
    }
}
