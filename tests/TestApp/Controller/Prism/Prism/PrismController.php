<?php
declare(strict_types=1);

namespace TestApp\Controller\Prism\Prism;

use TestApp\Controller\DemoAppController;

/**
 * Prism namespace-collision fixture (inner).
 */
class PrismController extends DemoAppController
{
    /**
     * Nested prism action.
     *
     * @return void
     */
    public function nested(): void
    {
    }
}
