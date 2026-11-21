<?php
declare(strict_types=1);

namespace TestApp\Controller\Prism;

use TestApp\Controller\DemoAppController;

/**
 * Prism namespace-collision fixture (outer).
 */
class PrismController extends DemoAppController
{
    /**
     * Prism index.
     *
     * @return void
     */
    public function index(): void
    {
    }
}
