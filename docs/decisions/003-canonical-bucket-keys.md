# ADR-003: Trends join on canonical machine keys; labels are presentation

## Status
Accepted.

## Context
Joining trend data on formatted human labels creates brittleness: locale-
dependent formats are fragile to parse in reverse, driver-specific SQL
quirks interfere with reliable round-tripping, and ISO-week edge cases
across multiple systems create silent mismatches. The join silently drops
non-matching rows instead of surfacing the inconsistency as a test failure.

## Decision
`Interval::key()` defines one canonical, locale-free key format per bucket
(`2026-07-10`, `2026-W28`, `2026-Q3`, …) and every `DateDialect` emits SQL
producing *exactly* that string. The `Timeline` generator produces the same
keys in PHP; gap-filling is a plain array union. Labels are derived from
the bucket start **after** merging, purely for display.

Bucket keys also solved the SQLite ISO-week problem honestly: `%G/%V`
require SQLite ≥ 3.46, so the dialect computes ISO year-week via the
"Thursday of the week" idiom, which works on any SQLite and provably
matches PHP's `isoWeekYear`/`isoWeek`.

## Alternatives considered
- *Formatted-label joins with better format specs.* Rejected: equality of
  formatted strings as a correctness invariant is the bug, not the format.
- *Joining on epoch timestamps of bucket starts.* Rejected: requires the
  database to compute timestamps of truncated dates portably, which is
  harder across five drivers than emitting a fixed string format.

## Consequences
Localization of labels becomes free (change `label()`, keys unaffected).
Mismatches between PHP and SQL bucketing show up as a failing test on key
format, not as silently missing data points. API consumers get stable,
sortable keys.
