<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Same URI served under several verbs.
 *
 * CakePHP equivalent of workbench SharedUriController (Laravel invokable
 * with GET+POST+PUT+PATCH on one URI): one action with a restrictive
 * `allowMethod()` list, wired to one explicit route per verb group.
 */
class SharedUriController extends DemoAppController
{
    /**
     * Handle the shared URI under any supported verb.
     *
     * @param string $name Resource name
     * @return void
     */
    public function handle(string $name): void
    {
        $this->request->allowMethod(['get', 'post', 'put', 'patch']);
    }
}
