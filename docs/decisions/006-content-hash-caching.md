# ADR-006: Cache plain data under content-hash keys

## Status
Accepted.

## Context
Nova caches whole result objects under keys built from the metric's
`uriKey` (a slug of its display name — renaming a metric orphans entries)
plus raw request inputs. Cached objects must survive PHP serialization,
which forced `SerializableClosure` gymnastics into result classes. There is
no invalidation API, no store selection, and custom inputs used inside
`calculate()` silently miss the key.

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

TTL semantics are Laravel's (seconds / `DateInterval` / `DateTimeInterface`)
— explicitly not Nova's surprising "numeric means minutes".

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
