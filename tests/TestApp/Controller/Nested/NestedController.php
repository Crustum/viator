<?php
declare(strict_types=1);

namespace TestApp\Controller\Nested;

use TestApp\Controller\DemoAppController;

/**
 * Nested controller fixture.
 */
class NestedController extends DemoAppController
{
    /**
     * Nested action.
     *
     * @return void
     */
    public function nested(): void
    {
    }

    /**
     * Child action.
     *
     * @return void
     */
    public function child(): void
    {
    }

    /**
     * Grandchild action.
     *
     * @return void
     */
    public function grandchild(): void
    {
    }
}
