<?php
declare(strict_types=1);

namespace TestApp\Controller;

use Cake\Controller\Controller;

/**
 * Fixture base controller with a shared action.
 *
 * Never generates its own file (AppController suffix rule), but `shared()`
 * surfaces through concrete subclasses.
 */
class DemoAppController extends Controller
{
    /**
     * Shared base action.
     *
     * @return void
     */
    public function shared(): void
    {
    }

    /**
     * Inertia render helper for fixtures.
     *
     * Never executed; only scanned by the types generator for
     * `->inertia('Component', [...])` calls.
     *
     * @param string $component Component path
     * @param array<string, mixed> $props Page props
     * @return array<string, mixed>
     */
    protected function inertia(string $component, array $props = []): array
    {
        return ['component' => $component, 'props' => $props];
    }
}
