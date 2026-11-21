<?php
declare(strict_types=1);

namespace TestApp\Enum;

/**
 * Pure unit enum.
 */
enum UnitEnum
{
    case None;
    case Open;
    case Done;

    /**
     * Human label.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'Not Started',
            self::Open => 'In Progress',
            self::Done => 'Finished',
        };
    }
}
