<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Model-binding equivalent: CakePHP resolves these as plain strings.
 */
class ModelBindingController extends DemoAppController
{
    /**
     * Show by id.
     *
     * @param string $user User id
     * @return void
     */
    public function show(string $user): void
    {
    }

    /**
     * Nullable binding equivalent.
     *
     * @param string $user User token
     * @return void
     */
    public function nullableBinding(string $user): void
    {
    }
}
