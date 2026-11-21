<?php
declare(strict_types=1);

namespace TestApp\Middleware;

use Cake\Http\ServerRequest;
use Crustum\InertiaCakephp\Middleware\InertiaMiddleware;

/**
 * Shared props fixture.
 *
 * Never registered in the application middleware stack; only scanned by
 * the shared-data collector for its `share()` shape.
 */
class SharedPropsMiddleware extends InertiaMiddleware
{
    /**
     * @param \Cake\Http\ServerRequest $request Current request
     * @return array<string, mixed>
     */
    public function share(ServerRequest $request): array
    {
        return [
            'locale' => 'en',
            'version' => 1,
        ];
    }
}
