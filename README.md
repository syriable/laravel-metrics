# Laravel Metrics — an API-first analytics engine

A standalone, backend-only metrics engine for Laravel. It computes **values,
trends, partitions, comparisons, multi-dataset metrics and formulas** — all
aggregated database-side — and returns normalized, serializer-friendly
structures ready for any consumer: REST, GraphQL, Filament, Vue, React,
Flutter, CLI.

No charts. No Blade. No Livewire. No widgets. Just an engine.

This package separates metrics computation from presentation. Define metrics as pure, reusable components that work everywhere — in API endpoints, queued reports, tests, and CLI commands — without coupling to HTTP requests, UI frameworks, or presentation concerns. The engine's architecture is guided by explicit design decisions documented in [ADRs](docs/decisions); see [docs/architecture.md](docs/architecture.md) for the full picture.

## Requirements

- PHP 8.4+
- Laravel 11 / 12 / 13
- MySQL, MariaDB, PostgreSQL, SQLite or SQL Server

## Installation

```bash
composer require syriable/laravel-metrics
php artisan vendor:publish --tag="laravel-metrics-config"   # optional
```

## Quick start

```php
use Syriable\Metrics\Facades\Metrics;

// One number, compared against the previous 30 days — in a single query.
$result = Metrics::query(Order::class)
    ->sum('total')
    ->range('30d')
    ->compareWithPrevious()
    ->value();

$result->value();                    // 48250.75
$result->comparison()->percentage;   // 12.4
$result->comparison()->direction;    // Direction::Up
$result->toArray();                  // normalized API payload
```

```php
// A gap-filled daily series.
$trend = Metrics::query(Order::class)
    ->count()
    ->range('mtd')
    ->perDay()
    ->trend();

// A group-by breakdown with engine-computed percentages.
$partition = Metrics::query(Order::class)
    ->count()
    ->groupBy('status')
    ->top(5)              // fold the tail into "others"
    ->partition();
```

## Concepts

| Concept | What it is |
|---|---|
| **Value** | one number per dataset, optionally compared against a reference period |
| **Trend** | a time series per dataset, gap-filled, bucketed by minute→year |
| **Partition** | one number per group per dataset (`groupBy`) |
| **Dataset** | one aggregation inside a metric; a metric can hold many |
| **Formula** | a computed dataset evaluated over the others, server-side |
| **Range** | a named resolver ("mtd", "30d") producing an immutable `Period` |
| **Comparison** | a strategy picking the reference window + uniform math (Δ, %, direction) |
| **Metric** | a reusable, named definition — a class extending `Metric`, scaffolded with `make:metric` and run by key |

## Ranges

Built-in keys: `today`, `yesterday`, `wtd`, `last_week`, `mtd`, `last_month`,
`qtd`, `last_quarter`, `ytd`, `last_year`, `all` — plus rolling patterns that
need no registration: `90m`, `24h`, `30d`, `4w`, `12mo`, `2q`, `5y`.

```php
->range('qtd')                                    // named
->range('12mo')                                   // rolling
->between('2026-01-01', '2026-06-30')             // explicit period
->allTime()                                       // unbounded
```

Calendar-aligned ranges compare like-for-like: `mtd` on July 10 compares
July 1–10 against **June 1–10**, not against the 10 days ending June 30.

## Trends

```php
Metrics::query(Order::class)
    ->average('total')
    ->range('ytd')
    ->per(Interval::Week)      // Minute | Hour | Day | Week | Month | Quarter | Year
    ->timezone('Asia/Kolkata') // minute-precision bucket shifting
    ->trend();
```

Every point carries a canonical machine key (`2026-W28`, `2026-07-10`,
`2026-Q3`), a human label, the bucket start, and the value. Missing buckets
are zero-filled with the aggregate's empty value (0 for count/sum, null for
avg/min/max). Series joining happens on machine keys — labels are pure
presentation.

## Comparisons

```php
->compareWithPrevious()          // immediately preceding period
->compareWithPreviousWeek()      // same window, shifted back
->compareWithPreviousMonth()     //   (no month-overflow surprises)
->compareWithPreviousQuarter()
->compareWithPreviousYear()
->compareWith($customStrategy)   // your own ComparisonStrategy
```

The engine computes `previous`, `difference`, `percentage` and `direction`
server-side. A value + comparison is **one SQL query** (conditional
aggregation), not two.

## Datasets & formulas

```php
$result = Metrics::query(Order::class)
    ->range('30d')
    ->dataset('revenue', fn ($d) => $d->sum('total'))
    ->dataset('refunds', fn ($d) => $d->sum('refund_total'))
    ->dataset('expenses', fn ($d) => $d->sum('amount')->from(Expense::class))
    ->formula('profit', '[revenue] - [refunds] - [expenses]')
    ->formula('margin', 'profit / revenue * 100')
    ->value();

$result->value('margin'); // 37.5
```

Formulas are parsed by a small, safe arithmetic evaluator (no `eval`, no SQL).
They work across all three metric shapes — per value, per trend point, per
partition group — and comparisons flow through them. Division by zero and
null operands yield `null` ("no data"), never an exception.

## Named metrics (the API story)

```php
class OrdersRevenue extends Metric
{
    public function query(): MetricBuilder
    {
        return Metrics::query(Order::class)
            ->sum('total')->range('30d')->compareWithPrevious()->cache(300);
    }
}

// e.g. in a controller:
Route::get('/api/metrics/{key}', function (string $key, Request $request) {
    return Metrics::run($key, $request->only(['range', 'interval', 'timezone', 'compare']));
});
```

No `Metrics::register()` call needed — any `Metric` subclass living under
the configured namespace/path (`App\Metrics` / `app/Metrics` by default,
whether hand-written or generated with `make:metric`) is discovered and
registered automatically at boot. Register manually only for metrics that
live elsewhere — a package, a different directory:

```php
Metrics::register(SomeOtherPackage\Metrics\Signups::class);
```

The package deliberately ships **no routes** — one line of your routing
exposes every registered metric, under your auth, your throttling, your
versioning.

## Generating metrics

```bash
php artisan make:metric Revenue
```

Scaffolds a new class in the configured metrics namespace (`App\Metrics` /
`app/Metrics` by default) from a publishable stub — the same experience as
`make:model` or `make:notification`:

```
INFO  Metric [app/Metrics/Revenue.php] created successfully.
```

Nested names respect PSR-4, just like every first-party generator:

```bash
php artisan make:metric Sales/Revenue   # App\Metrics\Sales\Revenue
```

`--force` overwrites an existing class; without it, `make:metric` refuses to
clobber your work. Everything else is configurable in `config/metrics.php`:

```php
'generator' => [
    'namespace' => 'App\\Metrics',   // the namespace generated classes declare
    'path' => app_path('Metrics'),  // where they're written
    'stub' => null,                 // an absolute path to fully override the stub
    'base_class' => Metric::class,  // the class generated metrics extend
],
```

Publish the stub to customize it in place — no config change required:

```bash
php artisan vendor:publish --tag="laravel-metrics-stubs"
```

Every metric generated this way is registered automatically — see
[Automatic discovery](#automatic-discovery). Set `metrics.discover` to
`false` if you'd rather register everything by hand.

Metric shapes are an open vocabulary, not a hardcoded switch: `make:metric`
discovers its `--{option}` flags from a blueprint registry, so a future
`--trend`/`--value`/`--partition` scaffold is registering a
`MetricBlueprint`, never editing the command.

## Automatic discovery

Every concrete `Metric` subclass under the configured namespace/path is
found and registered at boot — the same file-path-to-class discovery
Laravel uses for console commands in `app/Console/Commands`. No manifest,
no cache, no `Metrics::register()` call:

```php
// app/Metrics/Revenue.php — written by hand or by `make:metric` —
// is registered automatically. Just run it:
Metrics::run('revenue');
```

Turn it off in `config/metrics.php` if you'd rather register everything
explicitly (or have a very large metrics directory and want to avoid the
boot-time scan):

```php
'discover' => false,
```

## Caching

```php
->cache(600)      // seconds; any Laravel TTL value works
->fresh()         // bypass for one execution
```

Keys are hashed from the compiled SQL + bindings of every dataset plus the
resolved period/interval/timezone/formulas — changing anything about a
metric's definition is automatically a cache miss. Only plain arrays are
cached, never objects or closures. Store and global TTL are configurable in
`config/metrics.php`.

## Output

Every result serializes to the same normalized shape:

```json
{
    "key": "orders.revenue",
    "type": "trend",
    "range": {"key": "7d", "start": "2026-07-03T12:00:00+00:00", "end": "2026-07-10T12:00:00+00:00", "timezone": "UTC"},
    "interval": "day",
    "datasets": [
        {
            "name": "default",
            "points": [{"key": "2026-07-09", "label": "Jul 9, 2026", "start": "2026-07-09T00:00:00+00:00", "value": 30}],
            "total": 30,
            "formula": false,
            "comparison": {"strategy": "previous_period", "previous": 40, "difference": -10, "percentage": -25, "direction": "down"}
        }
    ],
    "meta": {},
    "generated_at": "2026-07-10T12:00:00+00:00",
    "from_cache": false
}
```

## Extending everything

```php
use Syriable\Metrics\Aggregates\CallbackAggregate;
use Syriable\Metrics\Ranges\CallbackRange;
use Syriable\Metrics\Comparisons\CallbackComparison;

// A new aggregation — no core changes:
Metrics::registerAggregate(new CallbackAggregate(
    'stddev', fn (string $inner) => "stddev({$inner})",
));

// A fiscal-year range:
Metrics::registerRange(new CallbackRange('fiscal_ytd', 'Fiscal YTD',
    fn (CarbonImmutable $now) => new Period($now->setMonth(4)->startOfMonth(), $now),
));

// A custom reference window:
Metrics::registerComparison(new CallbackComparison('vs_launch',
    fn (Period $current) => Period::between('2026-01-01', '2026-01-31'),
));

// A new database driver — one class:
Metrics::registerDialect(new FirebirdDialect);

// Your own payload shape / expression language:
Metrics::useSerializer(new JsonApiSerializer);
Metrics::useFormulaEvaluator(new SymfonyExpressionEvaluator);
```

## Performance notes

- Aggregation is always database-side; result rows are never hydrated into
  models (queries run through the base query builder).
- Value + comparison = 1 query. Trend = 1 grouped query per dataset.
  Partition = 1 grouped query per dataset.
- Range filtering stays on the raw indexed column (`whereBetween`); only the
  bucket key is computed per row.
- Timelines are generated lazily and capped (`metrics.max_buckets`) so a
  minute-over-a-year request fails fast instead of exhausting memory.
- `->withNow($instant)` pins the clock — deterministic tests and backfills
  without freezing global time.

## Testing

```bash
composer test
```

## Documentation

- [Architecture overview](docs/architecture.md)
- [Architecture decision records](docs/decisions)

## License

MIT — see [LICENSE.md](LICENSE.md).
