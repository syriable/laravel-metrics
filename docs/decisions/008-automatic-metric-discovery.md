# ADR-008: Metrics are discovered, not registered

## Status
Accepted.

## Context
The initial `Metrics::register()` API required every named metric to be
wired up by hand, typically in a service provider's `boot()` method. That's
one more manual step per metric, easy to forget, and it's exactly the kind
of bookkeeping Nova doesn't ask for — Nova auto-discovers every resource
under `app/Nova`. Laravel itself follows the same convention-over-registration
pattern for console commands: `Illuminate\Foundation\Console\Kernel::load()`
maps every file under `app/Console/Commands` to a class name and registers
it, with no manifest and no explicit opt-in per command.

## Decision
`MetricDiscoverer` walks the generator's configured namespace/path (the
same `generator.namespace`/`generator.path` config `make:metric` writes
to) with `Symfony\Component\Finder`, maps each file to a class name using
Laravel's own file-path-to-class-name convention, and keeps it if
reflection confirms it's a concrete `Metric` subclass. `MetricsServiceProvider::packageBooted()`
registers whatever comes back. This runs on every boot — no manifest file,
no `metrics:cache` command — because metrics directories are small (unlike
routes or events at application scale), matching Kernel::load()'s own
no-cache precedent for command discovery.

A single `metrics.discover` boolean (default `true`) is the only new
surface: on for the zero-config Nova-like experience, off for apps that
want explicit control or have reasons to avoid the boot-time scan.
`Metrics::register()` still exists and still works, unchanged — discovery
only covers the configured directory; metrics defined elsewhere (a
package, a different namespace) are registered the way they always were.

## Alternatives considered
- *Require explicit `Metrics::register()` always.* The status quo before
  this ADR; rejected as the one piece of ceremony left that Nova doesn't
  have, for a package whose stated goal is matching Nova's generator DX.
- *A cached discovery manifest (`bootstrap/cache/metrics.php`), mirroring
  `event:cache`.* Rejected for now: events and routes exist at a scale
  (hundreds, app-wide) where a per-request filesystem scan is measurably
  expensive; metrics don't. Revisit if real usage proves otherwise — the
  seam is `MetricDiscoverer::discover()`, one method, easy to wrap in a
  cache later without touching the service provider.
- *Auto-edit a service provider to insert `Metrics::register()` calls
  during `make:metric`.* Rejected: mutating a developer's existing PHP
  file from a generator is fragile (parsing, idempotency, merge conflicts)
  and something Laravel's own generators deliberately never do.

## Consequences
A metric written or generated under the configured directory works the
moment the file exists — `Metrics::run($key)`, no wiring step. Apps that
need to disable the scan (large trees, tighter boot-time budgets, or a
preference for explicit registration) set one config flag rather than
fighting the default.
