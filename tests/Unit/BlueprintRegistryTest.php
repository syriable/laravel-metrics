<?php

declare(strict_types=1);

use Syriable\Metrics\Console\Generators\BlueprintRegistry;
use Syriable\Metrics\Contracts\MetricBlueprint;
use Syriable\Metrics\Exceptions\UnknownBlueprintException;

final class FakeMetricBlueprint implements MetricBlueprint
{
    public function __construct(
        private readonly string $key,
        private readonly ?string $option,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function option(): ?string
    {
        return $this->option;
    }

    public function description(): string
    {
        return 'A fake blueprint for testing.';
    }

    public function stubPath(): string
    {
        return '/dev/null';
    }
}

it('registers the default blueprint out of the box', function (): void {
    $registry = new BlueprintRegistry;

    expect($registry->default()->key())->toBe('default')
        ->and($registry->get('default'))->toBe($registry->default());
});

it('registers additional blueprints without losing the default', function (): void {
    $registry = new BlueprintRegistry;
    $trend = new FakeMetricBlueprint('trend', 'trend');

    $registry->register($trend);

    expect($registry->all())->toHaveCount(2)
        ->and($registry->get('trend'))->toBe($trend)
        ->and($registry->default()->key())->toBe('default');
});

it('throws for an unknown blueprint key', function (): void {
    (new BlueprintRegistry)->get('nope');
})->throws(UnknownBlueprintException::class, 'Unknown metric blueprint [nope]');

it('resolves a blueprint from command options, falling back to the default', function (): void {
    $registry = new BlueprintRegistry;
    $trend = new FakeMetricBlueprint('trend', 'trend');
    $registry->register($trend);

    expect($registry->fromOptions(['trend' => true, 'force' => false]))->toBe($trend)
        ->and($registry->fromOptions(['trend' => false, 'force' => false]))->toBe($registry->default())
        ->and($registry->fromOptions(['force' => true]))->toBe($registry->default());
});
