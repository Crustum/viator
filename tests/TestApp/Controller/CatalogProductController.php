<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Entity-backed product fixture with Inertia pages.
 */
class CatalogProductController extends DemoAppController
{
    /**
     * List products.
     *
     * @return void
     */
    public function index(): void
    {
        $this->inertia('CatalogProducts/Index', ['products' => []]);
    }

    /**
     * Show one product.
     *
     * @param string $product Product id
     * @return void
     */
    public function show(string $product): void
    {
        $this->inertia('CatalogProducts/Show', ['product' => $product]);
    }
}
