<?php

declare(strict_types=1);

namespace Syriable\Metrics\Exceptions;

use InvalidArgumentException;

/**
 * Base type for every exception the package throws, so consumers can
 * catch the whole family with one clause.
 */
abstract class MetricsException extends InvalidArgumentException {}
