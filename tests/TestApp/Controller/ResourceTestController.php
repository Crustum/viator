<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Resource-style fixture mirroring workbench ResourceTestController.
 *
 * CakePHP has no JsonResource layer; the port keeps the same action names
 * and route shapes with plain responses.
 */
class ResourceTestController extends DemoAppController
{
    /**
     * Show one product.
     *
     * @param string $product Product id
     * @return void
     */
    public function show(string $product): void
    {
    }

    /**
     * List products.
     *
     * @return void
     */
    public function index(): void
    {
    }

    /**
     * Wrapped variant.
     *
     * @return void
     */
    public function wrapped(): void
    {
    }

    /**
     * Unwrapped variant.
     *
     * @return void
     */
    public function unwrapped(): void
    {
    }

    /**
     * JSON:API single resource.
     *
     * @param string $category Category id
     * @return void
     */
    public function jsonApi(string $category): void
    {
    }

    /**
     * JSON:API collection.
     *
     * @return void
     */
    public function jsonApiCollection(): void
    {
    }

    /**
     * Show one user.
     *
     * @param string $user User id
     * @return void
     */
    public function user(string $user): void
    {
    }

    /**
     * List users.
     *
     * @return void
     */
    public function users(): void
    {
    }

    /**
     * JSON:API user.
     *
     * @param string $user User id
     * @return void
     */
    public function userJsonApi(string $user): void
    {
    }
}
