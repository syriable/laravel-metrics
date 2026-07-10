<?php

declare(strict_types=1);

namespace Syriable\Metrics\Enums;

/**
 * The direction of a comparison between two values.
 */
enum Direction: string
{
    case Up = 'up';
    case Down = 'down';
    case Flat = 'flat';

    public static function of(int|float $difference): self
    {
        return match (true) {
            $difference > 0 => self::Up,
            $difference < 0 => self::Down,
            default => self::Flat,
        };
    }
}
