<?php

namespace Syriable\Metrics\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Syriable\Metrics\Metrics
 */
class Metrics extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Syriable\Metrics\Metrics::class;
    }
}
