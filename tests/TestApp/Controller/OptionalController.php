<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Optional-parameter fixture mirroring workbench OptionalController.
 */
class OptionalController extends DemoAppController
{
    /**
     * Single optional parameter, POST only.
     *
     * @param string|null $parameter Optional parameter
     * @return void
     */
    public function optional(?string $parameter = null): void
    {
        $this->request->allowMethod(['post']);
    }

    /**
     * Three optional parameters, POST only.
     *
     * @param string|null $one First filter
     * @param string|null $two Second filter
     * @param string|null $three Third filter
     * @return void
     */
    public function manyOptional(
        ?string $one = null,
        ?string $two = null,
        ?string $three = null,
    ): void {
        $this->request->allowMethod(['post']);
    }
}
