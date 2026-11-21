<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Anonymous-middleware equivalent (plain action; Cake wires middleware
 * in Application, not in the controller constructor).
 */
class AnonymousMiddlewareController extends DemoAppController
{
    /**
     * Show action.
     *
     * @return void
     */
    public function show(): void
    {
    }
}
