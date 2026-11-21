<?php
declare(strict_types=1);

namespace Crustum\Viator\Support;

use Crustum\Viator\Lang\TypeScript;
use Throwable;

/**
 * Enum method metadata mapped to TypeScript literals.
 *
 * Values the emitter cannot write stay out for that case alone, so the
 * rest of the case metadata still makes it through.
 */
final class EnumMeta
{
    /**
     * Write the values resolved for each case as TypeScript literals.
     *
     * A value that cannot be written as a literal is left out for that
     * case alone.
     *
     * @param array<string, array<string, mixed>> $meta Metadata by case and method
     * @return array<string, array<string, string>> Literals by case and method
     */
    public static function literals(array $meta): array
    {
        $literals = [];

        foreach ($meta as $case => $values) {
            foreach ($values as $method => $value) {
                try {
                    $literals[$case][$method] = self::encode($value);
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return $literals;
    }

    /**
     * Encode one metadata value as a TypeScript literal.
     *
     * @param mixed $value Metadata value
     * @return string TypeScript literal
     * @throws \Crustum\Viator\Support\UnsupportedEnumMetaValue When the value has no literal form
     */
    protected static function encode(mixed $value): string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return '[' . implode(', ', array_map(self::encode(...), $value)) . ']';
            }

            $pairs = [];

            foreach ($value as $key => $item) {
                $pairs[] = TypeScript::quoteKey((string)$key) . ': ' . self::encode($item);
            }

            return '{' . implode(', ', $pairs) . '}';
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            throw new UnsupportedEnumMetaValue();
        }

        return $encoded;
    }
}
