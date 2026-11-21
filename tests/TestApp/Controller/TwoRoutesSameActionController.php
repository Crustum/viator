<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * One action reachable via two URLs.
 *
 * Mirrors workbench TwoRoutesSameActionController.
 */
class TwoRoutesSameActionController extends DemoAppController
{
    /**
     * Shared action.
     *
     * @return void
     */
    public function same(): void
    {
    }
}
