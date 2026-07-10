# ADR-002: Execution context is explicit — the engine never sees a request

## Status
Accepted.

## Context
Nova's `calculate(NovaRequest $request)` reads `range`, `timezone`,
`twelveHourTime` and `filter` from the HTTP request *inside* the
computation, and helpers require the request as their first argument.
Running a metric from a job or test means faking requests; the cache key
must guess which request inputs matter.

## Decision
The builder carries the full execution context — range/period, interval,
timezone, comparison, precision, pinned `now` — as explicit, typed state.
HTTP integration is user-land glue: validated request input maps onto
`Metrics::run($key, $context)` with a whitelisted context array.

`->withNow()` deserves a note: "now" is data, not ambient state. Ranges
resolve against an injected instant, which makes range math deterministic
in tests (no `Carbon::setTestNow()` global mutation) and enables backfills
("compute this metric as of last Tuesday").

## Alternatives considered
- *Accept a request object but immediately convert it.* Rejected: it drags
  `illuminate/http` into the dependency graph and blesses one consumer
  shape over others.
- *A MetricContext DTO parameter on every call.* Considered seriously;
  folded into the builder instead because the builder already is the
  definition object and a second parallel object doubled the API surface.

## Consequences
Controllers shrink to a mapping line. Tests pin time per metric. The cache
key can enumerate everything that affects the numbers because everything
is explicit state.
