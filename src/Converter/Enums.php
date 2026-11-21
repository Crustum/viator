<?php
declare(strict_types=1);

namespace Crustum\Viator\Converter;

use Cake\Collection\CollectionInterface;
use Crustum\Scrutator\Component\Enum as EnumComponent;
use Crustum\Viator\Lang\TypeScript;
use Crustum\Viator\Result\Result;
use Crustum\Viator\Support\UnsupportedEnumMetaValue;
use Throwable;

/**
 * Native PHP enums to per-enum TypeScript files plus unions.
 *
 * Each collected enum renders one file following its PHP namespace
 * (`TestApp\Enum\PostStatus` becomes `TestApp/Enum/PostStatus.ts`): named
 * case constants, an inline const object, an `as const` assertion, and a
 * default export. Backed enums union their values, pure enums their
 * indices, empty enums `never`. Case names colliding with TypeScript
 * reserved words stay out of the individual constants but render as quoted
 * object entries with literal values. With methods enabled, no-argument
 * method results resolve per case into a `NameMeta` constant keyed by case
 * value, so a value handed back by the server works as the lookup key.
 */
final class Enums
{
    /**
     * @var bool
     */
    protected bool $withMethods = false;

    /**
     * Enable method resolution into `NameMeta` constants.
     *
     * @param bool $withMethods Whether to resolve methods
     * @return $this
     */
    public function withMethods(bool $withMethods = true): static
    {
        $this->withMethods = $withMethods;

        return $this;
    }

    /**
     * Convert collected enum components to dotted path unions.
     *
     * @param \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\Enum> $enums Enum components
     * @return array<string, string> Dotted FQN path to TS union
     */
    public function unions(CollectionInterface $enums): array
    {
        /** @var array<string, string> $found */
        $found = $enums->combine(
            fn(EnumComponent $enum): string => $this->dotted($enum),
            fn(EnumComponent $enum): string => $this->union($enum),
        )->toArray();

        ksort($found);

        return $found;
    }

    /**
     * Convert one collected enum to its TypeScript file.
     *
     * @param \Crustum\Scrutator\Component\Enum $enum Enum component
     * @return \Crustum\Viator\Result\Result File result
     */
    public function convert(EnumComponent $enum): Result
    {
        $name = $this->shortName($enum);

        /** @var \Cake\Collection\CollectionInterface<int, string> $lines */
        $lines = collection($this->constants($enum));
        $content = $lines->toList();
        if ($content !== []) {
            $content[] = '';
        }

        $content[] = $this->objectDeclaration($enum, $name);

        $meta = $this->withMethods ? $this->literals($enum->meta()) : [];
        if ($meta !== []) {
            $content[] = '';
            $content[] = $this->metaDeclaration($enum, $name, $meta);
        }

        $content[] = '';
        $content[] = 'export default ' . $name . ';';

        $path = str_replace('\\', '/', ltrim($enum->name, '\\'));

        return new Result($path . '.ts', implode("\n", $content));
    }

    /**
     * Read the dotted FQN path for a component.
     *
     * @param \Crustum\Scrutator\Component\Enum $enum Enum component
     * @return string Dotted FQN path
     */
    protected function dotted(EnumComponent $enum): string
    {
        return str_replace('\\', '.', ltrim($enum->name, '\\'));
    }

    /**
     * Read the short class name for a component.
     *
     * @param \Crustum\Scrutator\Component\Enum $enum Enum component
     * @return string Short name
     */
    protected function shortName(EnumComponent $enum): string
    {
        $name = ltrim($enum->name, '\\');
        $position = strrpos($name, '\\');

        return $position === false ? $name : substr($name, $position + 1);
    }

    /**
     * Build the TS union for a component: values, indices for pure enums.
     *
     * @param \Crustum\Scrutator\Component\Enum $enum Enum component
     * @return string TypeScript union
     */
    protected function union(EnumComponent $enum): string
    {
        if ($enum->cases === []) {
            return 'never';
        }

        /** @var \Cake\Collection\CollectionInterface<int, string> $literals */
        $literals = collection($enum->cases)
            ->filter(static fn(mixed $value): bool => $value !== null)
            ->map(fn(mixed $value): string => $this->literal($value))
            ->values();

        $union = TypeScript::union($literals->toList());

        return $union === 'unknown' ? 'never' : $union;
    }

    /**
     * Render one case value as a TypeScript literal.
     *
     * @param mixed $value Case value
     * @return string TypeScript literal
     */
    protected function literal(mixed $value): string
    {
        if (is_string($value)) {
            return TypeScript::quote($value);
        }

        return (string)$value;
    }

    /**
     * Render the individual case constants, skipping reserved names.
     *
     * @param \Crustum\Scrutator\Component\Enum $enum Enum component
     * @return array<int, string> Constant lines
     */
    protected function constants(EnumComponent $enum): array
    {
        /** @var \Cake\Collection\CollectionInterface<string, int|string|null> $cases */
        $cases = collection($enum->cases);

        /** @var \Cake\Collection\CollectionInterface<int, string> $lines */
        $lines = $cases
            ->reject(static fn(mixed $value, string $case): bool => in_array($case, TypeScript::RESERVED_KEYWORDS, true))
            ->map(fn(mixed $value, string $case): string => 'export const ' . $case . ' = ' . $this->literal($value))
            ->values();

        return $lines->toList();
    }

    /**
     * Render the const object declaration with its `as const` assertion.
     *
     * Entries matching their case name use the shorthand form; reserved
     * or quoted keys carry literal values.
     *
     * @param \Crustum\Scrutator\Component\Enum $enum Enum component
     * @param string $name Short enum name
     * @return string Declaration lines
     */
    protected function objectDeclaration(EnumComponent $enum, string $name): string
    {
        /** @var array<int, string> $entries */
        $entries = collection($enum->cases)
            ->map(fn(mixed $value, string $case): string => $this->entry($case, $value))
            ->values()
            ->toList();

        $object = $entries === [] ? '{}' : '{ ' . implode(', ', $entries) . ' }';
        $docblock = $this->docblock($enum);

        return ($docblock === '' ? '' : $docblock . "\n") . 'export const ' . $name . ' = ' . $object . ' as const';
    }

    /**
     * Render one const object entry.
     *
     * @param string $case Case name
     * @param mixed $value Case value
     * @return string Entry
     */
    protected function entry(string $case, mixed $value): string
    {
        $key = TypeScript::quoteKey($case);
        if ($key === $case && !in_array($case, TypeScript::RESERVED_KEYWORDS, true)) {
            return $key;
        }

        return $key . ': ' . $this->literal($value);
    }

    /**
     * Render the `NameMeta` constant for resolved method values.
     *
     * @param \Crustum\Scrutator\Component\Enum $enum Enum component
     * @param string $name Short enum name
     * @param array<string, array<string, string>> $meta Method literals keyed by case name
     * @return string Declaration lines
     */
    protected function metaDeclaration(EnumComponent $enum, string $name, array $meta): string
    {
        /** @var \Cake\Collection\CollectionInterface<int, string> $entries */
        $entries = collection($enum->cases)
            ->filter(static fn(mixed $value, string $case): bool => isset($meta[$case]))
            ->map(fn(mixed $value, string $case): string => TypeScript::quoteKey((string)$value) . ': ' . $this->objectBlock($this->methodPairs($meta[$case])))
            ->values();

        $docblock = $this->docblock($enum);

        return ($docblock === '' ? '' : $docblock . "\n") . 'export const ' . $this->metaName($name, $enum) . ' = ' . $this->objectBlock($entries->toList()) . ' as const';
    }

    /**
     * Render one level of method pairs as an indented block body.
     *
     * @param array<string, string> $pairs Method name to literal
     * @return array<int, string> Body lines
     */
    protected function methodPairs(array $pairs): array
    {
        /** @var \Cake\Collection\CollectionInterface<int, string> $lines */
        $lines = collection($pairs)
            ->map(static fn(string $literal, string $method): string => TypeScript::quoteKey($method) . ': ' . $literal)
            ->values();

        return $lines->toList();
    }

    /**
     * Wrap block body lines in braces with trailing-comma style.
     *
     * @param array<int, string> $lines Body lines
     * @return string Block
     */
    protected function objectBlock(array $lines): string
    {
        if ($lines === []) {
            return '{}';
        }

        /** @var \Cake\Collection\CollectionInterface<int, string> $indented */
        $indented = collection($lines)->map(static fn(string $line): string => '    ' . $line);

        return "{\n" . implode(",\n", $indented->toList()) . ",\n}";
    }

    /**
     * Read the `NameMeta` constant name, avoiding case name collisions.
     *
     * @param string $name Short enum name
     * @param \Crustum\Scrutator\Component\Enum $enum Enum component
     * @return string Constant name
     */
    protected function metaName(string $name, EnumComponent $enum): string
    {
        $taken = array_keys($enum->cases);
        $constant = $name . 'Meta';
        while (in_array($constant, $taken, true)) {
            $constant .= 'Meta';
        }

        return $constant;
    }

    /**
     * Write resolved method values as TypeScript literals.
     *
     * A value that cannot be written stays out for that case alone, so the
     * rest of the case metadata still renders.
     *
     * @param array<string, array<string, mixed>> $meta Resolved values keyed by case name
     * @return array<string, array<string, string>> Literals keyed by case name
     */
    protected function literals(array $meta): array
    {
        $literals = [];
        foreach ($meta as $case => $values) {
            foreach ($values as $method => $value) {
                try {
                    $literals[$case][$method] = $this->encode($value);
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return $literals;
    }

    /**
     * Encode one resolved value as a TypeScript literal.
     *
     * @param mixed $value Resolved value
     * @return string TypeScript literal
     * @throws \Crustum\Viator\Support\UnsupportedEnumMetaValue When the value has no literal form
     */
    protected function encode(mixed $value): string
    {
        if (is_array($value)) {
            /** @var \Cake\Collection\CollectionInterface<int, string> $items */
            $items = collection($value)->map(fn(mixed $item): string => $this->encode($item));

            if (array_is_list($value)) {
                return '[' . implode(', ', $items->toList()) . ']';
            }

            /** @var \Cake\Collection\CollectionInterface<int, string> $pairs */
            $pairs = $items->map(static fn(string $literal, int|string $key): string => TypeScript::quoteKey((string)$key) . ': ' . $literal);

            return '{ ' . implode(', ', $pairs->toList()) . ' }';
        }

        if (is_string($value)) {
            return TypeScript::quote($value);
        }

        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string)$value;
        }

        if (is_float($value) && is_finite($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return is_string($encoded) ? $encoded : throw new UnsupportedEnumMetaValue();
        }

        throw new UnsupportedEnumMetaValue();
    }

    /**
     * Render the `@see` docblock linking the PHP enum.
     *
     * @param \Crustum\Scrutator\Component\Enum $enum Enum component
     * @return string Docblock, empty when the file path is unknown
     */
    protected function docblock(EnumComponent $enum): string
    {
        $file = $this->componentFile($enum);
        if ($file === null) {
            return '';
        }

        $class = '\\' . ltrim($enum->name, '\\');
        $path = TypeScript::relativeDocPath($file);
        $docblock = TypeScript::dockblock(['@see [' . $class . '](' . $path . ')']);

        return str_replace(["\r\n", "\r"], "\n", $docblock);
    }

    /**
     * Read the component file path, if the collector set one.
     *
     * @param \Crustum\Scrutator\Component\Enum $enum Enum component
     * @return string|null File path
     */
    protected function componentFile(EnumComponent $enum): ?string
    {
        try {
            $file = $enum->filePath();
        } catch (Throwable) {
            return null;
        }

        return $file === '' ? null : $file;
    }
}
