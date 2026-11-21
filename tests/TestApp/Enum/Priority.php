<?php
declare(strict_types=1);

namespace TestApp\Enum;

/**
 * Integer-backed priority.
 */
enum Priority: int
{
    case Low = 1;
    case High = 2;

    /**
     * Human label.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::High => 'High',
        };
    }

    /**
     * Weight multiplier.
     *
     * @return float
     */
    public function multiplier(): float
    {
        return match ($this) {
            self::Low => 0.5,
            self::High => 2.5,
        };
    }
}
