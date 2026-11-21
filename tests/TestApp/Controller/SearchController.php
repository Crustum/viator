<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Search fixture (CakePHP has no QUERY verb; GET is the equivalent).
 */
class SearchController extends DemoAppController
{
    /**
     * Search index.
     *
     * @return void
     */
    public function index(): void
    {
    }

    /**
     * Search by type.
     *
     * @param string $type Search type
     * @return void
     */
    public function show(string $type): void
    {
    }
}
