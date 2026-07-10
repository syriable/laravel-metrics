# ADR-003: Trends join on canonical machine keys; labels are presentation

## Status
Accepted.

## Context
Nova gap-fills trends by generating localized human labels ("July 9, 2026",
"March 2026") in PHP, re-parsing SQL bucket strings with
`Carbon::createFromFormat`, re-formatting them into the same label format,
and merging on **string equality of translated labels** — silently dropping
rows whose labels don't match (`reject(fn => ! in_array($key, ...))`).
Locale, driver quirks and ISO-week edge cases all threaten the join.

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
- *Nova's label-join with better formats.* Rejected: equality of formatted
  strings as a correctness invariant is the bug, not the format.
- *Joining on epoch timestamps of bucket starts.* Rejected: requires the
  database to compute timestamps of truncated dates portably, which is
  harder across five drivers than emitting a fixed string format.

## Consequences
Localization of labels becomes free (change `label()`, keys unaffected).
Mismatches between PHP and SQL bucketing show up as a failing test on key
format, not as silently missing data points. API consumers get stable,
sortable keys.
