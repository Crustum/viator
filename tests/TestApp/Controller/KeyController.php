<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Key-parameter fixture mirroring workbench KeyController.
 *
 * CakePHP has no model binding keys; both params stay strings.
 */
class KeyController extends DemoAppController
{
    /**
     * Show by key.
     *
     * @param string $key Record key
     * @return void
     */
    public function show(string $key): void
    {
    }

    /**
     * Edit by key.
     *
     * @param string $key Record key
     * @return void
     */
    public function edit(string $key): void
    {
    }
}
