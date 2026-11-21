<?php
declare(strict_types=1);

namespace Crustum\Viator\Lang\TypeScript;

use Crustum\Viator\Lang\Trait\CanExportTrait;
use Crustum\Viator\Lang\Trait\HasMetaTrait;

/**
 * One `export type X = ...;` / `export const X = ...;` declaration.
 *
 * Centralizes Viator's declaration rendering (export flags plus optional
 * docblock metadata) instead of hand-concatenating the same shape in
 * every converter.
 */
final class Declaration
{
    use CanExportTrait;
    use HasMetaTrait;

    /**
     * @param string $keyword Declaration keyword (`type` or `const`)
     * @param string $name Declared name
     * @param string $value Declared value
     */
    private function __construct(
        protected string $keyword,
        protected string $name,
        protected string $value,
    ) {
    }

    /**
     * Build a type declaration.
     *
     * @param string $name Type name
     * @param string $value Type value
     * @return static
     */
    public static function type(string $name, string $value): static
    {
        return new self('type', $name, $value);
    }

    /**
     * Build a const declaration.
     *
     * @param string $name Const name
     * @param string $value Const value
     * @return static
     */
    public static function constant(string $name, string $value): static
    {
        return new self('const', $name, $value);
    }

    /**
     * Render the declaration with its export flag and metadata.
     *
     * @return string
     */
    public function render(): string
    {
        $meta = $this->meta();
        $prefix = $meta === '' ? '' : $meta . PHP_EOL;

        return $prefix . $this->exportFormatted() . "{$this->keyword} {$this->name} = {$this->value};";
    }
}
