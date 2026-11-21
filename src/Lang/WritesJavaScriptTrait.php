<?php
declare(strict_types=1);

namespace Crustum\Viator\Lang;

/**
 * JavaScript string emission shared by the language layer.
 *
 * Consumed by `Lang\TypeScript` instead of reimplementing quoting and
 * splitting in every converter.
 */
trait WritesJavaScriptTrait
{
    /**
     * Indent every line by one level (four spaces) per level.
     *
     * @param string $string Multiline content
     * @param int $level Indentation depth
     * @return string
     */
    public static function indent(string $string, int $level = 1): string
    {
        $lines = explode(PHP_EOL, $string);
        $indented = array_map(static fn(string $line): string => str_repeat(' ', $level * 4) . $line, $lines);

        return implode(PHP_EOL, $indented);
    }

    /**
     * Quote a string as a TypeScript double-quoted literal.
     *
     * Slashes and unicode stay literal; U+2028/U+2029 stay escaped for
     * JavaScript safety.
     *
     * @param string $string Raw value
     * @return string Quoted literal including the quotes
     */
    public static function quote(string $string): string
    {
        $encoded = json_encode($string, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($encoded)) {
            $encoded = '""';
        }

        return str_replace(["\u{2028}", "\u{2029}"], ['\u2028', '\u2029'], $encoded);
    }

    /**
     * Escape backticks and template placeholders for template literals.
     *
     * @param string $string Raw value
     * @return string
     */
    public static function escapeTemplateLiteral(string $string): string
    {
        return str_replace(['\\', '`', '${'], ['\\\\', '\\`', '\\${'], $string);
    }

    /**
     * Quote an object key only when it is not a plain identifier.
     *
     * Bracket-prefixed keys (computed keys) pass through untouched.
     *
     * @param string $key Raw key
     * @return string Bare identifier or quoted literal
     */
    public static function quoteKey(string $key): string
    {
        if (str_starts_with($key, '[')) {
            return $key;
        }

        if (preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $key) === 1) {
            return $key;
        }

        return self::quote($key);
    }

    /**
     * Split on a separator at bracket depth zero.
     *
     * @param string $str Raw value
     * @param string $separator Single-character separator
     * @return array<int, string> Trimmed segments
     */
    protected static function splitOn(string $str, string $separator): array
    {
        $segments = [];
        $depth = 0;
        $current = '';

        foreach (str_split($str) as $character) {
            if (in_array($character, ['<', '(', '['], true)) {
                $depth++;
            } elseif (in_array($character, ['>', ')', ']'], true)) {
                $depth--;
            }

            if ($character === $separator && $depth === 0) {
                $segments[] = trim($current);
                $current = '';
            } else {
                $current .= $character;
            }
        }

        if ($current !== '') {
            $segments[] = trim($current);
        }

        return $segments;
    }
}
