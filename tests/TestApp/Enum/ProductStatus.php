<?php
declare(strict_types=1);

namespace TestApp\Enum;

/**
 * Backed product status with reserved-word case names.
 */
enum ProductStatus: string
{
    case new = 'new';
    case used = 'used';
    case for = 'for-sale';
    case Active = 'active';

    /**
     * Human label.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::new => 'Brand New',
            self::used => 'Pre-Owned',
            self::for => 'For Sale',
            self::Active => 'Active',
        };
    }

    /**
     * Whether this is the default status.
     *
     * @return bool
     */
    public function default(): bool
    {
        return $this === self::Active;
    }
}
