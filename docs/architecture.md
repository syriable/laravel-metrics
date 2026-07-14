# Architecture

This document explains how the engine is put together and why. Individual
decisions, with alternatives considered, live in [decisions/](decisions).

## The one-sentence design

**Immutable definition + context in → pure result out**, with every
vocabulary (aggregates, dialects, ranges, comparisons, serializers, formula
evaluators) open through registries, and HTTP/UI strictly outside the
engine boundary.

## Component map

```
                       ┌─────────────────────────────┐
 your app / API  ───►  │ Metrics (manager)           │  registries + entry points
                       │  query() · register() · run()│
                       └──────────┬──────────────────┘
                                  ▼
                       MetricBuilder / DatasetBuilder    pure description, no I/O
                                  │  value()/trend()/partition()/get()
                                  ▼
                       ┌─────────────────────────────┐
                       │ MetricEngine (orchestrator) │
                       │                             │
                       │  RangeEngine  ── Range/Period│  when
                       │  QueryEngine  ── Aggregate,  │  what   (the only SQL)
                       │                  DateDialect │
                       │  Timeline     ── Interval    │  gap-fill on machine keys
                       │  Formulas     ── evaluator   │  computed datasets
                       │  Comparison   ── strategy +  │  Δ, %, direction
                       │                  Comparison VO│
                       │  CacheEngine  ── content-hash│  plain arrays only
                       └──────────┬──────────────────┘
                                  ▼
                       MetricResult / DatasetResult /
                       TrendPoint / PartitionGroup        immutable, computed once
                                  │ toArray()
                                  ▼
                       Serializer (swappable projection)
```

### Layer responsibilities

| Layer | Files | Owns | Never touches |
|---|---|---|---|
| Manager | `Metrics` | registries, config, entry points | SQL, dates |
| Builders | `Builder/*` | describing a metric | DB, clock, cache |
| Engines | `Engine/*` | orchestration, SQL, ranges, caching | HTTP, presentation |
| Vocabulary | `Aggregates/*`, `Time/Dialects/*`, `Ranges/*`, `Comparisons/*`, `Formulas/*` | one open concept each | each other |
| Values/Results | `Support/*`, `Results/*` | immutable data | I/O of any kind |
| Projection | `Serializers/*` | payload shape | computation |

## Execution flow (trend with comparison, worst case)

1. `MetricBuilder` hands itself to `MetricEngine::run()`.
2. Context resolution: display timezone → storage timezone → pinned or real
   `now` → `RangeEngine` resolves the range input into `[rangeKey, Period]`
   → the comparison strategy picks the reference `Period`.
3. Validation: trends and comparisons require a bounded period; aggregates
   requiring columns must have one. Typed exceptions, no silent clamping.
4. Cache check: the key hashes each dataset's compiled SQL + bindings and
   the full execution context. Hit → skip to 7.
5. Per dataset: `QueryEngine.trend()` runs **one grouped query** — the date
   dialect renders the bucket-key expression (with a minute-precision
   timezone offset), the aggregate renders the aggregation expression, the
   period bounds are bound as plain parameters on the raw indexed column.
   If comparing, `QueryEngine.value()` runs one more conditional-aggregation
   query for the totals.
6. `Timeline` lazily generates the canonical bucket keys for the period and
   the raw values are merged onto it (gap-filling); formulas are evaluated
   pointwise; everything is rounded. The result is a plain array — this is
   what gets cached.
7. Assembly: plain arrays become `TrendPoint`s (labels generated here, from
   the same `Interval` that generated the keys), `Comparison`s compute Δ/%/
   direction once, and the immutable `MetricResult` is returned.
8. `toArray()`/`json_encode` delegate to the configured `Serializer` — a
   pure projection.

## The load-bearing decisions

Six decisions carry most of the architecture; each has a full ADR:

1. **Definitions are not cards** ([ADR-001](decisions/001-engine-not-cards.md)) —
   no UI vocabulary anywhere in the engine.
2. **Context is explicit, never read from a request**
   ([ADR-002](decisions/002-context-not-request.md)) — jobs, commands, tests
   and controllers all call the same API.
3. **Machine keys join, labels present**
   ([ADR-003](decisions/003-canonical-bucket-keys.md)) — gap-filling can't
   silently drop rows.
4. **Open vocabularies via registries**
   ([ADR-004](decisions/004-registries-over-inheritance.md)) — adding an
   aggregate/range/driver/strategy is registration, not subclassing.
5. **One query per question**
   ([ADR-005](decisions/005-single-query-comparisons.md)) — conditional
   aggregation folds current + previous into one scan.
6. **Cache data, not objects**
   ([ADR-006](decisions/006-content-hash-caching.md)) — content-hash keys,
   plain-array payloads, automatic invalidation on definition change.

## Timezone model

Three explicit timezones, each with one job:

- **display** (`->timezone()`, `metrics.timezone`, app tz): where range
  boundaries and bucket edges live.
- **storage** (`metrics.storage_timezone`, app tz): what's actually in the
  datetime columns. Period bounds are converted display → storage before
  binding.
- **bucket shifting**: the dialect receives `displayOffset − storageOffset`
  in **minutes** (half-hour and 45-minute zones work), evaluated at
  execution time. Known limitation: the offset is constant across the
  queried range, so a range spanning a DST transition mis-buckets rows near
  the boundary by one hour. Fixing it properly requires session/native tz
  conversion per driver (`CONVERT_TZ`, `AT TIME ZONE`) — the dialect seam
  is where that lands without touching anything else.

## Security posture

- Raw SQL fragments are assembled only from: grammar-wrapped identifiers,
  expressions emitted by registered `Aggregate`/`DateDialect` objects, and
  `?` placeholders with bindings.
- The formula engine is a hand-rolled shunting-yard arithmetic parser —
  no `eval()`, no callables, no database access; enforced by an
  architecture test.
- Period bounds are always bound parameters, never interpolated.

## Performance posture

- No model hydration anywhere (`toBase()` before every execution).
- One grouped query per dataset per trend/partition; one conditional query
  per dataset per compared value.
- Lazy `Generator`-based timelines with a hard bucket cap.
- Rounding normalizes integer-valued floats back to ints for compact JSON.
- The cache boundary sits around the *computation* (queries + gap-fill +
  formulas), not around object assembly, so cached payloads are small and
  version-independent.

## What this engine intentionally does not do

- **Ship HTTP endpoints** — one route in user land beats a router in
  package land (auth, throttling and versioning belong to the app).
- **Render anything** — no chart hints, colors, number formats; a
  serializer can add presentation metadata if a consumer needs it.
- **Median/percentile built-ins** — portable SQL for them doesn't exist
  across all five supported drivers; `CallbackAggregate` covers the
  databases that support them natively (one line), which beats shipping
  driver-conditional core code.
