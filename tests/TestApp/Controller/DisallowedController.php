<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Reserved-word fixture.
 *
 * PHP cannot declare `default`, `public`, `static`, `404` or `2fa` methods,
 * so the CakePHP equivalent keeps valid PHP method names while the named
 * routes below carry the reserved last segments (`disallowed.404`,
 * `disallowed.default`, ...). The generator sanitizes those into
 * `method404`, `defaultMethod`, etc. via `safeMethod()`.
 * `delete()` stays as the actions/ reserved-word case (`deleteMethod`).
 */
class DisallowedController extends DemoAppController
{
    /**
     * Reserved action name, suffixed in TypeScript.
     *
     * @return void
     */
    public function delete(): void
    {
    }

    /**
     * Target for the `disallowed.404` named route.
     *
     * @return void
     */
    public function notFound(): void
    {
    }

    /**
     * Target for the `disallowed.2fa` named route.
     *
     * @return void
     */
    public function twoFa(): void
    {
    }

    /**
     * Target for the `disallowed.default` named route.
     *
     * @return void
     */
    public function login(): void
    {
    }

    /**
     * Target for the `disallowed.public` named route.
     *
     * @return void
     */
    public function asset(): void
    {
    }

    /**
     * Target for the `disallowed.static` named route.
     *
     * @return void
     */
    public function file(): void
    {
    }
}
