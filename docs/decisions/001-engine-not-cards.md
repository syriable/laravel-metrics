# ADR-001: Metric definitions are engine inputs, not UI cards

## Status
Accepted.

## Context
Nova's `Value extends RangedMetric extends Metric extends Card extends
Element`: every metric carries a Vue component name, a card width, an icon,
help-text tooltips and numbro format strings. Computing "orders this month"
requires instantiating a dashboard widget. The result classes serialize
frontend-specific shapes, and percentages/colors are computed during
`jsonSerialize()`.

## Decision
Separate three concerns into three object families that never mix:

- **definition** — `MetricBuilder`/`DatasetBuilder`/`Metric`: pure
  description, no I/O, no presentation;
- **execution** — the engines: everything computed exactly once;
- **projection** — `Serializer`: a stateless mapping of an immutable
  result to a payload.

No class in the package knows about components, widths, icons, colors or
number formats. Presentation metadata a consumer needs can ride in the
free-form `meta()` bag or a custom serializer.

## Alternatives considered
- *Keep a "card" layer as optional sugar.* Rejected: it recreates the
  coupling this package exists to remove; Nova/Filament adapters belong in
  separate bridge packages.
- *Result objects with fluent presentation setters (Nova's `->dollars()`).*
  Rejected: mutable results make caching and testing harder and drag
  currency/formatting into an engine that shouldn't own it.

## Consequences
The same metric definition serves an API controller, a queued report, a
console command and a test without adaptation. UI packages consume the
normalized payload instead of the engine's internals.
