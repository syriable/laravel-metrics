# ADR-004: Open vocabularies via registries, not class hierarchies

## Status
Accepted.

## Context
Closed vocabularies force every new capability to be baked into the core:
hardcoded strings dispatched by method name, switch statements for ranges,
missing concepts entirely, and extensibility limited to one or two seams.
Adding a new aggregate, range, or comparison strategy means editing multiple
core classes, risking regressions and coupling new features to core code.

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
What would otherwise be hardcoded method dispatch becomes a single `->aggregate($key, $column)` call plus readable shorthands. Unknown keys fail with typed exceptions that name the registration method. A new database driver is one dialect class, not edits across multiple core files.
