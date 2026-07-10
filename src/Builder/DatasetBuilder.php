<?php

declare(strict_types=1);

namespace Syriable\Metrics\Builder;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Configures one dataset of a metric: which aggregate, over which column,
 * with which extra constraints — and optionally from a different source
 * than the metric's default (so one metric can combine revenue from orders
 * with expenses from purchases).
 */
final class DatasetBuilder
{
    private string $aggregate = 'count';

    private ?string $column = null;

    private ?Closure $scope = null;

    private ?string $dateColumn = null;

    private Builder|Model|string|Closure|null $source = null;

    public function __construct(private readonly string $name) {}

    public function count(?string $column = null): self
    {
        return $this->aggregate('count', $column);
    }

    public function countDistinct(string $column): self
    {
        return $this->aggregate('count_distinct', $column);
    }

    public function sum(string $column): self
    {
        return $this->aggregate('sum', $column);
    }

    public function average(string $column): self
    {
        return $this->aggregate('avg', $column);
    }

    public function avg(string $column): self
    {
        return $this->average($column);
    }

    public function min(string $column): self
    {
        return $this->aggregate('min', $column);
    }

    public function max(string $column): self
    {
        return $this->aggregate('max', $column);
    }

    /**
     * Use any registered aggregate by key.
     */
    public function aggregate(string $key, ?string $column = null): self
    {
        $this->aggregate = $key;
        $this->column = $column;

        return $this;
    }

    /**
     * Constrain this dataset's query on top of the metric-level scope.
     *
     * @param  Closure(Builder): (Builder|null|void)  $scope
     */
    public function query(Closure $scope): self
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * Read this dataset from a different model/query than the metric's
     * default source.
     *
     * @param  Builder|Model|class-string<Model>|Closure(): Builder  $source
     */
    public function from(Builder|Model|string|Closure $source): self
    {
        $this->source = $source;

        return $this;
    }

    /**
     * Use a different date column than the metric's default.
     */
    public function dateColumn(string $column): self
    {
        $this->dateColumn = $column;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function aggregateKey(): string
    {
        return $this->aggregate;
    }

    public function columnName(): ?string
    {
        return $this->column;
    }

    public function scope(): ?Closure
    {
        return $this->scope;
    }

    public function source(): Builder|Model|string|Closure|null
    {
        return $this->source;
    }

    public function dateColumnName(): ?string
    {
        return $this->dateColumn;
    }
}
