<?php

declare(strict_types=1);

namespace Syriable\Metrics\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Syriable\Metrics\Console\Generators\BlueprintRegistry;
use Syriable\Metrics\Console\Generators\MetricGenerator;
use Syriable\Metrics\Exceptions\MetricsException;

/**
 * `php artisan make:metric {name}` — scaffolds a new Metric class from a
 * stub, with the same developer experience as `make:model`,
 * `make:event` and `make:notification`.
 *
 * This command only orchestrates: name validation, namespacing, path
 * resolution, stub rendering and file writing all live in
 * Console\Generators. Shape options (--trend, --value, …) are discovered
 * from the BlueprintRegistry at construction time, so registering a new
 * MetricBlueprint is enough to grow both a flag here and a stub — this
 * class never needs to change.
 */
final class MetricMakeCommand extends Command
{
    protected $signature = 'make:metric {name : The name of the metric}
                            {--force : Overwrite the metric if it already exists}';

    protected $description = 'Create a new metric class';

    public function __construct(private readonly BlueprintRegistry $blueprints)
    {
        parent::__construct();

        $this->addBlueprintOptions();
    }

    public function handle(MetricGenerator $generator): int
    {
        $blueprint = $this->blueprints->fromOptions($this->options());

        try {
            $result = $generator->generate(
                (string) $this->argument('name'),
                $blueprint,
                (bool) $this->option('force'),
            );
        } catch (MetricsException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $path = $this->relativePath($result->path);

        if (! $result->created) {
            $this->components->error(sprintf('Metric [%s] already exists.', $path));

            return self::FAILURE;
        }

        $this->components->info(sprintf('Metric [%s] created successfully.', $path));

        return self::SUCCESS;
    }

    /**
     * Grow the command's options with one flag per blueprint that declares
     * one — the seam that lets --trend, --value, --partition, --table and
     * --heatmap show up later without touching this class.
     */
    private function addBlueprintOptions(): void
    {
        foreach ($this->blueprints->all() as $blueprint) {
            $option = $blueprint->option();

            if ($option === null || $this->getDefinition()->hasOption($option)) {
                continue;
            }

            $this->getDefinition()->addOption(new InputOption(
                $option,
                null,
                InputOption::VALUE_NONE,
                $blueprint->description(),
            ));
        }
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
