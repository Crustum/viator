<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Fixture trait with a helper method that is not a routable action.
 */
trait DemoHelperTrait
{
    /**
     * Trait helper, must be skipped via the ignore list.
     *
     * @return array<int, mixed>
     */
    public function fetchCollection(): array
    {
        return [];
    }
}
