# ADR-007: `make:metric` is an orchestrator over a blueprint registry

## Status
Accepted.

## Context
Nova's `nova:metric` generator is a single command class that string-matches
`--trend`/`--value`/`--partition` options inline and branches to a
hardcoded stub path for each. Adding a new metric shape means editing the
command itself. This package's own philosophy (ADR-004) already rejects
that pattern for the runtime engine's vocabularies — the generator should
not reintroduce it for metric *shapes*.

## Decision
Generation is split the same way the engine is: small single-responsibility
collaborators under `Console\Generators`, wired together by a command that
only orchestrates.

| Class | Owns |
|---|---|
| `MetricMakeCommand` | signature, options, console output |
| `MetricGenerator` | the generate-one-metric workflow |
| `NamespaceResolver` | name → fully-qualified class name |
| `PathResolver` | fully-qualified class name → file path |
| `StubResolver` | stub lookup (override chain) + placeholder rendering |
| `FileWriter` | existence checks and disk writes |
| `BlueprintRegistry` | key/option → `MetricBlueprint`, open for registration |

A `MetricBlueprint` is a small interface (`key()`, `option()`,
`description()`, `stubPath()`) rather than a subclass of the command or the
generator. The command builds its own `--{option}` flags at construction
time by reading `BlueprintRegistry::all()`, so shipping `--trend` later is
registering a `TrendMetricBlueprint` — `MetricMakeCommand` and
`MetricGenerator` never change. Only one blueprint (`DefaultMetricBlueprint`)
exists today; the registry exists so that isn't a rewrite later.

The bundled stub avoids `use` imports for anything the generated class's
own name could plausibly collide with (the base class, the placeholder
example model) — `extends \Fully\Qualified\Name` instead of `use` + short
name — because `make:metric User` or `make:metric Metric` are real inputs,
and a colliding import is a fatal "Cannot use X as Y" at require-time, not
a lint nitpick. Pint still cleans this up into a normal import for every
non-colliding name, which is the common case.

## Alternatives considered
- *Extend `Illuminate\Console\GeneratorCommand`.* Rejected: it collapses
  name validation, path resolution, stub lookup and file writing into one
  class's protected methods, which is exactly the coupling this ADR avoids
  reintroducing at the shape level. The command instead composes its own
  collaborators.
- *A `--type=trend` option with a switch statement.* Rejected for the same
  reason aggregates aren't a switch statement (ADR-004): closed by
  construction, and the command would need editing for every addition.
- *Base-class-per-shape stub relying on `use` imports.* Rejected after
  finding it generates uncompilable code for `make:metric User`.

## Consequences
Adding a metric shape is one new class plus one registration call. The
command's help output, its flags, and the generator's stub selection all
update from that single registration — nothing else in the generator is
aware shapes exist.
