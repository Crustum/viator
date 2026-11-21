<?php
declare(strict_types=1);

namespace Crustum\Viator\Validation;

use Crustum\Viator\Lang\TypeScript;

/**
 * One CakePHP validator field mapped to TypeScript.
 *
 * A key is required only with `requirePresence()` and without any
 * `allowEmpty*()` marker (`notEmpty*()` alone does not require the key
 * to be present). Field types resolve from type-ish validator methods and
 * `add()` rule names, defaulting to `string`. Literal `inList` values win
 * as the narrowest inline union; otherwise an enum rule references the
 * shared enum type instead of inlining its values.
 */
final class Rules
{
    /**
     * @param bool $required Whether the key must be present
     * @param array<int, string> $kinds Type signals, e.g. `integer`, `email`, `uploadedFile`
     * @param array<int, string>|null $inList Literal `inList` values, if any
     * @param string|null $enumType Enum class behind an `enum` rule, if any
     */
    public function __construct(
        protected bool $required,
        protected array $kinds = [],
        protected ?array $inList = null,
        protected ?string $enumType = null,
    ) {
    }

    /**
     * Whether the key is required.
     *
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Resolve the TypeScript field type.
     *
     * @return string
     */
    public function resolveFieldType(): string
    {
        if (is_array($this->inList)) {
            if ($this->inList === []) {
                return 'string';
            }

            return TypeScript::union(array_map(TypeScript::quote(...), $this->inList));
        }

        if (is_string($this->enumType) && $this->enumType !== '') {
            return str_replace('\\', '.', ltrim($this->enumType, '\\'));
        }

        $kinds = array_map(strtolower(...), $this->kinds);

        if (array_intersect($kinds, ['uploadedfile', 'file', 'image', 'mimetype', 'allowemptyfile']) !== []) {
            return 'File';
        }

        if (array_intersect($kinds, ['integer', 'numeric', 'naturalnumber', 'float']) !== []) {
            return 'number';
        }

        if (in_array('boolean', $kinds, true)) {
            return 'boolean';
        }

        if (in_array('decimal', $kinds, true)) {
            return '`${number}.${number}`';
        }

        if (array_intersect($kinds, ['array', 'isarray', 'allowemptyarray']) !== []) {
            return 'unknown[]';
        }

        return 'string';
    }
}
