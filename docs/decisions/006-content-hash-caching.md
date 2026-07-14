# ADR-006: Cache plain data under content-hash keys

## Status
Accepted.

## Context
Caching result objects with keys based on display names or request slugs
creates fragility: renaming a metric orphans cache entries, serialization
requirements couple internal result classes to storage concerns, and custom
execution inputs used inside metric computations silently miss the cache key
entirely. There's no portable invalidation strategy and no way to select
alternate cache stores without affecting all metrics.

## Decision
The cache boundary wraps the *computation* (queries, gap-fill, formulas,
rounding) and stores **plain arrays only**. The key is
`prefix:sha256(json)` over: metric type, resolved period + reference
period, interval, timezone, groupBy/top, formulas, precision, and — the
important part — each dataset's **compiled SQL string + bindings**
(`fingerprint()`).

Because scopes, sources, and filters all end up in the SQL, any change to
what would be queried is automatically a different key. Two metrics that
would run identical queries share an entry. Nothing about naming affects
keys.

TTL semantics follow Laravel conventions: seconds, `DateInterval`, or
`DateTimeInterface` instances — the same as Laravel's own cache store.

## Alternatives considered
- *Hash the builder's definition (closures included).* Rejected: closures
  don't hash; compiled SQL is the truthful, canonical form of "what will
  run".
- *Cache the `MetricResult` object.* Rejected: ties cache entries to class
  internals (poison on deploys), requires serializable everything.
- *Cache tags for invalidation.* Deferred: tags don't work on file/database
  stores; `CacheEngine::forget()` plus deterministic keys covers targeted
  invalidation portably.

## Consequences
Definition changes self-invalidate. Cached payloads survive package
upgrades (they're arrays, not objects). One trade-off, accepted knowingly:
building the key requires compiling each dataset's base query (cheap — no
execution), and the time-window itself is part of the key, so rolling
ranges ("last 30 days" ending *now*) produce a new key each second —
callers wanting coarser granularity pin `->withNow($now->startOfMinute())`.
