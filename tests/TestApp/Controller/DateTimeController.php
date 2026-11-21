<?php
declare(strict_types=1);

namespace TestApp\Controller;

use DateTime;
use DateTimeImmutable;

/**
 * DateTime props fixture mirroring workbench DateTimeController.
 */
class DateTimeController extends DemoAppController
{
    /**
     * Page with date props.
     *
     * @return void
     */
    public function show(): void
    {
        $this->inertia('DateTime/Show', [
            'immutable' => new DateTimeImmutable(),
            'mutable' => new DateTime(),
        ]);
    }
}
