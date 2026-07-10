# ADR-004: Open vocabularies via registries, not class hierarchies

## Status
Accepted.

## Context
Nova's vocabularies are closed: aggregates are five hard-coded strings
dispatched by method name (25 hand-written helpers on `Trend` alone),
ranges are a switch statement, comparisons don't exist as a concept, and
only the trend date expression factory is extensible (via `Macroable`).
Adding "distinct count" means editing four metric classes.

## Decision
Every vocabulary is a small interface plus a runtime registry on the
manager:

| Concept | Contract | Register with |
|---|---|---|
| aggregation | `Aggregate` | `registerAggregate()` |
| DB driver | `DateDialect` | `registerDialect()` |
| named range | `Range` | `registerRange()` |
| comparison | `ComparisonStrategy` | `registerComparison()` |
| payload shape | `Serializer` | `useSerializer()` |
| formulas | `FormulaEvaluator` | `useFormulaEvaluator()` |

Each contract ships a callback-based implementation (`CallbackAggregate`,
`CallbackRange`, `CallbackComparison`) so one-off extensions are a closure,
not a class. Built-ins register through the same public API — the package
is its own first extension consumer, which keeps the seams honest.

## Alternatives considered
- *Laravel's `Macroable`.* Rejected for core seams: macros are untyped,
  undiscoverable, and can't be validated at registration.
- *Container tagging / auto-discovery.* Rejected as primary mechanism:
  implicit wiring hurts debuggability; explicit registration in a service
  provider is one line.
- *An enum of aggregates.* Rejected: enums are closed by definition — the
  exact property we're avoiding.

## Consequences
The 25-helper matrix collapses into `->aggregate($key, $column)` plus a
handful of readable shorthands. Unknown keys fail with typed exceptions
that name the registration method. A sixth database is one class.
