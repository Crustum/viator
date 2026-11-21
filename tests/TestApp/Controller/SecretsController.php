<?php
declare(strict_types=1);

namespace TestApp\Controller;

use Crustum\Viator\Attribute\ViatorIgnore;

/**
 * Secrets fixture mirroring workbench SecretsController.
 */
class SecretsController extends DemoAppController
{
    /**
     * Secrets page.
     *
     * @return void
     */
    public function index(): void
    {
        $this->inertia('Secrets', [
            'name' => 'Alex',
            'socialSecurityNumber' => '000-00-0000', // @viator-ignore
            'account' => [
                'label' => 'Primary',
                // @viator-ignore
                'routingNumber' => '000000000',
            ],
            'email' => 'alex@example.com',
        ]);
    }

    /**
     * Internal-only action, never generated.
     *
     * @return void
     */
    #[ViatorIgnore]
    public function reveal(): void
    {
        $this->inertia('Reveal', ['internalOnly' => 'value']);
    }

    /**
     * Resource variant.
     *
     * @return void
     */
    public function resource(): void
    {
    }
}
