<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Modular (`Authorization::`) component fixture.
 */
class ModularInertiaController extends DemoAppController
{
    /**
     * Modular login page.
     *
     * @return void
     */
    public function login(): void
    {
        $this->inertia('Authorization::Login', ['status' => 'ready']);
    }

    /**
     * Modular register page.
     *
     * @return void
     */
    public function register(): void
    {
        $this->inertia('Authorization::Register', ['terms' => true]);
    }
}
