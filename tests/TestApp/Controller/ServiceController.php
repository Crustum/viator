<?php
declare(strict_types=1);

namespace TestApp\Controller;

use TestApp\Dto\WidgetData;
use TestApp\Service\ReportService;

/**
 * Fixture controller with non-URL action parameters.
 */
class ServiceController extends DemoAppController
{
    /**
     * Request-object parameter, resolved from request data.
     *
     * @param \TestApp\Dto\WidgetData $data Validated data
     * @return void
     */
    public function register(WidgetData $data): void
    {
    }

    /**
     * Container service plus a genuine URL parameter.
     *
     * @param \TestApp\Service\ReportService $service Injected service
     * @param string $id Record id
     * @return void
     */
    public function report(ReportService $service, string $id): void
    {
    }
}
