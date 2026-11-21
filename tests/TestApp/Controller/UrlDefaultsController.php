<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * URL-defaults fixture.
 *
 * CakePHP equivalent of workbench UrlDefaultsController + middleware:
 * values come from route defaults, so actions declare no URL params
 * (mirroring the workbench signature).
 */
class UrlDefaultsController extends DemoAppController
{
    /**
     * Only defaults.
     *
     * @return void
     */
    public function onlyDefaults(): void
    {
        $this->request->allowMethod(['post']);
    }

    /**
     * Mixed defaults and params.
     *
     * @param string $timezone Timezone param
     * @return void
     */
    public function mixedDefaults(string $timezone): void
    {
        $this->request->allowMethod(['post']);
    }

    /**
     * Quoted default.
     *
     * @return void
     */
    public function quotedDefaults(): void
    {
        $this->request->allowMethod(['post']);
    }
}
