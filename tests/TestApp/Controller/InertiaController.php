<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Inertia pages fixture mirroring workbench InertiaController.
 */
class InertiaController extends DemoAppController
{
    /**
     * Dashboard page.
     *
     * @return void
     */
    public function dashboard(): void
    {
        $this->inertia('Dashboard', [
            'stats' => ['users' => 100, 'posts' => 50, 'views' => 10000],
            'recentActivity' => [],
        ]);
    }

    /**
     * Users list page.
     *
     * @return void
     */
    public function usersList(): void
    {
        $this->inertia('UsersList', [
            'users' => [],
            'featured' => null,
        ]);
    }

    /**
     * Settings page.
     *
     * @return void
     */
    public function settings(): void
    {
        $this->inertia('Settings/General', [
            'user' => ['name' => 'John Doe', 'email' => 'john@example.com'],
            'preferences' => ['theme' => 'dark', 'notifications' => true],
        ]);
    }

    /**
     * Profile page.
     *
     * @return void
     */
    public function profile(): void
    {
        $this->inertia('Profile/Show', [
            'profile' => ['bio' => 'Hello world', 'avatar' => null],
        ]);
    }

    /**
     * Lowercase/hyphen component name.
     *
     * @return void
     */
    public function unsafe(): void
    {
        $this->inertia('settings/two-factor', [
            'user' => ['name' => 'Jane Doe', 'email' => 'jane@doe.co'],
        ]);
    }

    /**
     * Conditional branches.
     *
     * @return void
     */
    public function conditional(): void
    {
        if ($this->request->is('get')) {
            $this->inertia('Conditional/Authenticated', ['user' => null]);

            return;
        }

        $this->inertia('Conditional/Guest', ['canLogin' => true]);
    }
}
