<?php
declare(strict_types=1);

namespace TestApp\Enum;

/**
 * Empty enum renders as `never`.
 */
enum EmptyEnum: string
{
    /**
     * Human label.
     *
     * @return string
     */
    public function label(): string
    {
        return 'Nothing';
    }
}
