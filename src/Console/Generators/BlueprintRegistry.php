<?php

declare(strict_types=1);

namespace Syriable\Metrics\Console\Generators;

use Syriable\Metrics\Blueprints\DefaultMetricBlueprint;
use Syriable\Metrics\Contracts\MetricBlueprint;
use Syriable\Metrics\Exceptions\UnknownBlueprintException;

/**
 * Open vocabulary of metric scaffolds `make:metric` can generate.
 *
 * Shipping a new shape (--trend, --value, --partition, --table, --heatmap)
 * is registering a MetricBlueprint here — MetricMakeCommand and
 * MetricGenerator never change. Bound as a singleton, so apps can register
 * their own blueprints too, e.g. in a service provider's boot method:
 *
 *     app(BlueprintRegistry::class)->register(new TrendMetricBlueprint);
 */
class BlueprintRegistry
{
    /** @var array<string, MetricBlueprint> */
    private array $blueprints = [];

    public function __construct()
    {
        $this->register(new DefaultMetricBlueprint);
    }

    public function register(MetricBlueprint $blueprint): static
    {
        $this->blueprints[$blueprint->key()] = $blueprint;

        return $this;
    }

    public function get(string $key): MetricBlueprint
    {
        return $this->blueprints[$key] ?? throw UnknownBlueprintException::forKey($key);
    }

    public function default(): MetricBlueprint
    {
        return $this->get('default');
    }

    /**
     * @return array<string, MetricBlueprint>
     */
    public function all(): array
    {
        return $this->blueprints;
    }

    /**
     * Resolve which blueprint a set of resolved command options selects;
     * falls back to the default blueprint when no option-backed blueprint
     * was requested.
     *
     * @param  array<string, mixed>  $options  option name => value, as returned by Command::options()
     */
    public function fromOptions(array $options): MetricBlueprint
    {
        foreach ($this->blueprints as $blueprint) {
            if ($blueprint->option() !== null && ($options[$blueprint->option()] ?? false)) {
                return $blueprint;
            }
        }

        return $this->default();
    }
}
