<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Navigation-item fixture.
 */
class NavigationItemController extends DemoAppController
{
    /**
     * Options action (also tests `routeOptions` collision safety).
     *
     * @param string $item Item id
     * @return void
     */
    public function options(string $item): void
    {
    }
}
