<?php
declare(strict_types=1);

namespace Crustum\Viator\Converter;

use Crustum\Investigator\Type\ArrayType;
use Crustum\Investigator\Type\Contract\Type as TypeContract;
use Crustum\Viator\Lang\TypeScript;
use Crustum\Viator\Registry\TypeScriptConverter;

/**
 * Base converter over Scrutator components.
 *
 * Holds the P2 type converter plus the shared mixed-map renderers (raw
 * `mixed` maps from response components hold analyzed types, pre-rendered
 * TS strings, or plain values).
 */
abstract class Converter
{
    /**
     * @var \Crustum\Viator\Registry\TypeScriptConverter|null
     */
    protected ?TypeScriptConverter $typeConverter = null;

    /**
     * Read the P2 type converter.
     *
     * @return \Crustum\Viator\Registry\TypeScriptConverter
     */
    protected function converter(): TypeScriptConverter
    {
        if (!$this->typeConverter instanceof TypeScriptConverter) {
            $this->typeConverter = new TypeScriptConverter();
        }

        return $this->typeConverter;
    }

    /**
     * Indent every line by one level.
     *
     * @param string $content Multiline content
     * @return string
     */
    protected function indent(string $content): string
    {
        return '    ' . str_replace("\n", "\n    ", $content);
    }

    /**
     * Render one mixed map value to TypeScript.
     *
     * Analyzed types convert through P2; strings stay pre-rendered TS (the
     * upstream cast semantics for `additional` members); plain scalars
     * become literals; anything else becomes `unknown` rather than a
     * wrong type.
     *
     * @param mixed $value Raw map value
     * @return string TypeScript type
     */
    protected function mixedToTs(mixed $value): string
    {
        if ($value instanceof TypeContract) {
            return $this->typeString($value);
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            return $this->objectLiteral($value);
        }

        return 'unknown';
    }

    /**
     * Render a mixed map as an inline object type.
     *
     * Single-line comma style matches the upstream payload strings embedded
     * in route function types (`{ data: { id: number } }`).
     *
     * @param array<array-key, mixed> $data Prop to raw value map
     * @return string Object type
     */
    protected function inlineObject(array $data): string
    {
        if ($data === []) {
            return 'Record<string, never>';
        }

        ksort($data);
        $parts = [];
        foreach ($data as $prop => $value) {
            $parts[] = TypeScript::quoteKey((string)$prop) . ': ' . $this->inlineMixedToTs($value);
        }

        return '{ ' . implode(', ', $parts) . ' }';
    }

    /**
     * Convert an analyzed type, normalizing newlines for emit.
     *
     * The P2 converter follows the host line ending; generated files always
     * use LF. Continuation lines take one extra indent level so nested
     * shapes align under their key.
     *
     * @param \Crustum\Investigator\Type\Contract\Type $type Analyzed type
     * @param string $continuationPad Pad for continuation lines, empty for none
     * @return string TypeScript type
     */
    protected function typeString(TypeContract $type, string $continuationPad = ''): string
    {
        $ts = str_replace(["\r\n", "\r"], "\n", $this->converter()->convert($type));
        if ($continuationPad !== '' && str_contains($ts, "\n")) {
            $ts = str_replace("\n", "\n" . $continuationPad, $ts);
        }

        return $ts;
    }

    /**
     * Render one mixed map value as an inline type.
     *
     * Mappable `ArrayType` values normalize to the inline object so payload
     * strings stay single-line no matter which input shape the collector
     * produced (the P2 converter owns the multiline block style used in
     * namespace bodies).
     *
     * @param mixed $value Raw map value
     * @return string TypeScript type
     */
    protected function inlineMixedToTs(mixed $value): string
    {
        if ($value instanceof ArrayType && !array_is_list($value->value)) {
            return $this->inlineObject($value->value);
        }

        if ($value instanceof TypeContract) {
            return $this->typeString($value);
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            return $this->inlineObject($value);
        }

        return 'unknown';
    }

    /**
     * Render a mixed map as an inline object type.
     *
     * @param array<array-key, mixed> $data Prop to raw value map
     * @return string Object type
     */
    protected function objectLiteral(array $data): string
    {
        if ($data === []) {
            return '{}';
        }

        ksort($data);
        $lines = ['{'];
        foreach ($data as $prop => $value) {
            $lines[] = '    ' . TypeScript::quoteKey((string)$prop) . ': ' . $this->mixedToTs($value) . ';';
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }
}
