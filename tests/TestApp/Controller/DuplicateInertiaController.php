<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Duplicate-component fixture for Inertia deduplication.
 */
class DuplicateInertiaController extends DemoAppController
{
    /**
     * Renders Dashboard without props.
     *
     * @return void
     */
    public function duplicate(): void
    {
        $this->inertia('Dashboard');
    }

    /**
     * Renders an already-seen component with props.
     *
     * @return void
     */
    public function duplicateWithData(): void
    {
        $this->inertia('Settings/General', [
            'user' => ['name' => 'John Doe', 'email' => 'john@example.com'],
        ]);
    }
}
