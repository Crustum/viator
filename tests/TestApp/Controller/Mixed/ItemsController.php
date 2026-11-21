<?php
declare(strict_types=1);

namespace TestApp\Controller\Mixed;

use TestApp\Controller\DemoAppController;

/**
 * Prefixed mixed-route fixture mirroring workbench MixedRouteController.
 */
class ItemsController extends DemoAppController
{
    /**
     * List items with an Inertia page.
     *
     * @return void
     */
    public function index(): void
    {
        $this->inertia('Items', ['items' => []]);
    }

    /**
     * Edit one item with an Inertia page.
     *
     * @param int $item Item id
     * @return void
     */
    public function edit(int $item): void
    {
        $this->inertia('Items/Edit', ['item' => ['id' => $item]]);
    }

    /**
     * Update one item.
     *
     * @param int $item Item id
     * @return void
     */
    public function update(int $item): void
    {
        $this->request->allowMethod(['patch', 'put']);
        $this->inertia('Items/Edit', ['item' => ['id' => $item]]);
    }
}
