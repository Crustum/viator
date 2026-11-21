<?php
declare(strict_types=1);

namespace Crustum\Viator\Routing;

use Crustum\Viator\Lang\TypeScript;

/**
 * Single URL parameter of a route definition.
 *
 * CakePHP resolves every parameter to `string | number`; model binding
 * with custom key types is intentionally left out.
 */
class Parameter
{
    /**
     * Placeholder as it appears in the URI template (`{name}` / `{name?}`).
     *
     * @var string
     */
    public string $placeholder;

    /**
     * TypeScript type union.
     *
     * @var string
     */
    public string $types;

    /**
     * @param string $name Parameter name
     * @param bool $optional Whether the parameter may be omitted
     * @param string|null $default Default value, if any
     */
    public function __construct(
        public string $name,
        public bool $optional,
        public ?string $default = null,
    ) {
        $this->placeholder = $optional ? "{{$name}?}" : "{{$name}}";
        $this->types = 'string | number';
    }

    /**
     * Collision-safe TypeScript identifier.
     *
     * @return string
     */
    public function safeName(): string
    {
        return TypeScript::safeMethod($this->name, 'Param');
    }
}
