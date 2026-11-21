<?php
declare(strict_types=1);

namespace Crustum\Viator\Routing;

/**
 * HTTP verb of a route with its form-safe variant.
 *
 * Forms only submit `get` or `post`; other verbs tunnel through `_method`.
 */
class Verb
{
    /**
     * Form-safe variant of the verb.
     *
     * @var string
     */
    public string $formSafe;

    /**
     * @param string $actual Actual HTTP verb
     */
    public function __construct(public string $actual)
    {
        $this->actual = strtolower($actual);
        $this->formSafe = in_array(strtolower($actual), ['get', 'head', 'options'], true) ? 'get' : 'post';
    }
}
