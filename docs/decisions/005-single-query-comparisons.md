# ADR-005: Value comparisons run as one conditional-aggregation query

## Status
Accepted.

## Context
Nova computes value metrics with two aggregate queries (current window,
previous window) — two scans over the same index on large tables, twice the
round-trip latency, and a consistency window between the two reads.

## Decision
When a comparison is requested, `QueryEngine::value()` emits a single
query:

```sql
select sum(case when created_at between ?cur_start and ?cur_end then total end)  as current_value,
       sum(case when created_at between ?prev_start and ?prev_end then total end) as previous_value
from orders
where created_at between ?overall_start and ?overall_end
```

The outer `WHERE` keeps the scan index-friendly and bounded to the union of
both windows; the `CASE` expressions split rows per window inside the
aggregate. Column-less aggregates (count) use `then 1`; `count(distinct
case … end)` handles distinct counts. This works identically on MySQL,
MariaDB, PostgreSQL, SQLite and SQL Server.

## Alternatives considered
- *Two queries (Nova).* Kept only as the degenerate cases: no comparison,
  or all-time metrics.
- *`FILTER (WHERE …)` clauses.* Cleaner SQL but PostgreSQL/SQLite only;
  a per-driver split for zero measured benefit over CASE.
- *UNION ALL of two aggregates.* Same scans as two queries, saved only the
  round-trip.

## Consequences
Half the queries and one consistent snapshot per compared value. Trend
totals comparisons reuse the same code path (one extra query per dataset,
instead of two).
