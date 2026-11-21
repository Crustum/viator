<?php
declare(strict_types=1);

namespace TestApp\Enum\Concerns;

/**
 * Shared icon helper mirroring workbench HasIcon.
 */
trait HasIconTrait
{
    /**
     * Icon name for the case value.
     *
     * @return string
     */
    public function icon(): string
    {
        return 'icon-' . $this->value;
    }
}
