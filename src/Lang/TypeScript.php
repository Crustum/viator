<?php
declare(strict_types=1);

namespace Crustum\Viator\Lang;

use Cake\Utility\Inflector;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * TypeScript emission helpers.
 *
 * Shared string behavior comes from the `WritesJavaScriptTrait` (quoting,
 * splitting); identifier helpers (`safeMethod`, `quoteIfNeeded`) live here
 * next to the Cake-native PHPDoc mapping (casing delegates to
 * `Cake\Utility\Inflector`). Files render through Twig templates and
 * per-converter string builders instead of a global type graph.
 */
final class TypeScript
{
    use WritesJavaScriptTrait;

    /**
     * Reserved TypeScript keywords.
     *
     * @var array<int, string>
     */
    public const RESERVED_KEYWORDS = [
        'await',
        'break',
        'case',
        'catch',
        'class',
        'const',
        'continue',
        'debugger',
        'default',
        'delete',
        'do',
        'else',
        'enum',
        'export',
        'extends',
        'false',
        'finally',
        'for',
        'function',
        'if',
        'implements',
        'import',
        'in',
        'instanceof',
        'interface',
        'let',
        'new',
        'null',
        'package',
        'private',
        'protected',
        'public',
        'return',
        'static',
        'super',
        'switch',
        'this',
        'throw',
        'true',
        'try',
        'typeof',
        'var',
        'void',
        'while',
        'with',
        'yield',
    ];

    /**
     * Make a string safe to use as a TypeScript method name.
     *
     * @param string $method Raw name
     * @param string $suffix Suffix used when the name is reserved or invalid
     * @return string
     */
    public static function safeMethod(string $method, string $suffix): string
    {
        $method = preg_replace('/[^\p{L}\p{Nd}_$-]/u', '_', $method) ?? $method;

        if (str_contains($method, '-')) {
            $method = Inflector::variable($method);
        }

        $suffix = strtolower($suffix);

        if (in_array($method, self::RESERVED_KEYWORDS, true)) {
            return $method . ucfirst($suffix);
        }

        if (!preg_match('/^[a-zA-Z_$]/', $method)) {
            return $suffix . $method;
        }

        return $method;
    }

    /**
     * Quote a name only when it is not a valid identifier.
     *
     * @param string $name Raw name
     * @return string
     */
    public static function quoteIfNeeded(string $name): string
    {
        if (is_numeric($name)) {
            return $name;
        }

        if (is_numeric($name[0])) {
            return '"' . $name . '"';
        }

        return $name;
    }

    /**
     * Render a docblock from metadata lines.
     *
     * @param array<int, string> $meta Metadata lines without comment markers
     * @return string
     */
    public static function dockblock(array $meta): string
    {
        $lines = array_map(static fn(string $line): string => " * {$line}", $meta);

        return '/**' . PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL . ' */';
    }

    /**
     * Read a docblock path relative to the project root with slashes.
     *
     * Absolute machine paths never leak into generated files: paths under
     * the project root render root-relative, anything else renders with
     * a leading slash and forward slashes.
     *
     * @param string $path Absolute file path
     * @return string Root-relative path, e.g. `/tests/TestApp/Enum/Status.php`
     */
    public static function relativeDocPath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        if (defined('ROOT')) {
            $root = rtrim(str_replace('\\', '/', (string)constant('ROOT')), '/') . '/';
            if ($root !== '/' && str_starts_with($normalized, $root)) {
                return '/' . substr($normalized, strlen($root));
            }
        }

        return '/' . ltrim($normalized, '/');
    }

    /**
     * Join types into a union, defaulting to `unknown` when empty.
     *
     * @param array<int, string> $types Type strings
     * @return string
     */
    public static function union(array $types): string
    {
        $types = array_values(array_unique($types));

        return $types === [] ? 'unknown' : implode(' | ', $types);
    }

    /**
     * Wrap content in backticks.
     *
     * @param string $content Raw content
     * @return string
     */
    public static function backtick(string $content): string
    {
        return '`' . $content . '`';
    }

    /**
     * Wrap content in a `${...}` template placeholder.
     *
     * @param string $content Raw content
     * @return string
     */
    public static function templateString(string $content): string
    {
        return '${' . $content . '}';
    }

    /**
     * Map a PHPDoc type string to TypeScript.
     *
     * Handles nullable prefixes, top-level unions, `array`/`list` generics,
     * and entity references (`App\Model\Entity\X` becomes
     * `App.Model.Entity.X`). Unknown shapes become `unknown`.
     *
     * @param string $type PHPDoc type, e.g. `?int`, `array<string, int>`, `\App\Model\Entity\Note[]`
     * @param string $appNamespace Host app namespace for entity references
     * @return string
     */
    public static function fromPhpDoc(string $type, string $appNamespace = 'App'): string
    {
        $nullable = false;
        if (str_starts_with($type, '?')) {
            $nullable = true;
            $type = substr($type, 1);
        }

        $mapped = [];
        foreach (self::splitOn($type, '|') as $part) {
            $part = trim($part);
            if ($part === '' || strtolower($part) === 'null') {
                $nullable = true;
                continue;
            }

            $mapped[] = self::singleToTs($part, $appNamespace);
        }

        $mapped = array_values(array_unique($mapped));
        if ($mapped === []) {
            $mapped[] = 'unknown';
        }

        $ts = implode(' | ', $mapped);
        if ($nullable && !in_array('null', explode(' | ', $ts), true)) {
            $ts .= ' | null';
        }

        return $ts;
    }

    /**
     * Map a single (non-union) PHPDoc type to TypeScript.
     *
     * @param string $type Single PHPDoc type
     * @param string $appNamespace Host app namespace for entity references
     * @return string
     */
    protected static function singleToTs(string $type, string $appNamespace): string
    {
        $arrayOf = false;
        if (str_ends_with($type, '[]')) {
            $arrayOf = true;
            $type = substr($type, 0, -2);
        }

        if (preg_match('/^(.+?)<(.+)>$/s', $type, $match)) {
            $base = strtolower(trim($match[1]));
            $args = array_map(trim(...), self::splitOn($match[2], ','));
            if (in_array($base, ['array', 'non-empty-array'], true) && count($args) === 2) {
                $mapped = 'Record<' . self::fromPhpDoc($args[0], $appNamespace) . ', ' . self::fromPhpDoc($args[1], $appNamespace) . '>';
            } elseif (in_array($base, ['array', 'list', 'non-empty-list'], true) && count($args) === 1) {
                $mapped = 'Array<' . self::fromPhpDoc($args[0], $appNamespace) . '>';
            } else {
                $mapped = 'unknown';
            }

            return $arrayOf ? "Array<{$mapped}>" : $mapped;
        }

        $lower = strtolower(ltrim($type, '\\'));
        $mapped = match ($lower) {
            'int', 'integer', 'float', 'double', 'number' => 'number',
            'string', 'str' => 'string',
            'bool', 'boolean' => 'boolean',
            'array', 'list' => 'Array<unknown>',
            'mixed', 'object', 'callable', 'closure', 'resource', 'scalar', 'iterable' => 'unknown',
            'false', 'true' => $lower,
            'datetime', 'datetimeimmutable', 'datetimeinterface',
            'cake\\i18n\\datetime', 'cake\\i18n\\date', 'cake\\i18n\\frozen_time',
            'cake\\chronos\\chronos', 'date' => 'string',
            default => self::classToTs($type, $appNamespace),
        };

        return $arrayOf ? "Array<{$mapped}>" : $mapped;
    }

    /**
     * Map a class reference: `*\Model\Entity\X` mirrors its PHP namespace
     * (`App.Model.Entity.X`, `MyPlugin.Model.Entity.X`), as do `*\Form\*Form`
     * classes. Anything else is unknown.
     *
     * @param string $type Class name as written in the docblock
     * @param string $appNamespace Host app namespace (kept for signature compatibility)
     * @return string
     */
    protected static function classToTs(string $type, string $appNamespace): string
    {
        $trimmed = ltrim($type, '\\');
        if (preg_match('/^(.+)\\\\Model\\\\Entity\\\\([^\\\\]+)$/', $trimmed, $match)) {
            return str_replace('\\', '.', $match[1]) . '.Model.Entity.' . $match[2];
        }

        if (preg_match('/^(.+)\\\\Form\\\\([^\\\\]*Form)$/', $trimmed, $match)) {
            return str_replace('\\', '.', $match[1]) . '.Form.' . $match[2];
        }

        return 'unknown';
    }

    /**
     * Render a reflection type as a PHPDoc type string.
     *
     * @param \ReflectionType $type Reflection type
     * @return string PHPDoc equivalent, e.g. `?int`, `string|int`
     */
    public static function reflectionToPhpDoc(ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            if ($name === 'null') {
                return 'null';
            }

            if ($name === 'mixed') {
                return 'mixed';
            }

            if ($type->allowsNull() && !in_array($name, ['mixed', 'null'], true)) {
                return '?' . $name;
            }

            return $name;
        }

        if ($type instanceof ReflectionUnionType) {
            $parts = [];
            foreach ($type->getTypes() as $inner) {
                $rendered = self::reflectionToPhpDoc($inner);
                if ($inner instanceof ReflectionNamedType && $inner->allowsNull() && !str_starts_with($rendered, '?')) {
                    $rendered = '?' . $rendered;
                }

                $parts[] = ltrim($rendered, '?');
                if (str_starts_with($rendered, '?')) {
                    $parts[] = 'null';
                }
            }

            return implode('|', array_unique($parts));
        }

        return 'unknown';
    }

    /**
     * Nest a leaf value under path segments, returning the new tree.
     *
     * Value-based alternative to by-reference tree building, which static
     * analysis cannot follow. Used for dotted namespace trees (channel
     * consts, enum roots).
     *
     * @param array<string, mixed> $tree Existing tree
     * @param array<int, string> $segments Path segments, leaf included
     * @param mixed $leaf Leaf value
     * @return array<string, mixed> New tree
     */
    public static function nestValue(array $tree, array $segments, mixed $leaf): array
    {
        $key = array_shift($segments);
        if (!is_string($key) || $key === '') {
            return $tree;
        }

        if ($segments === []) {
            $tree[$key] = $leaf;

            return $tree;
        }

        $node = isset($tree[$key]) && is_array($tree[$key]) ? $tree[$key] : [];
        $tree[$key] = self::nestValue($node, $segments, $leaf);

        return $tree;
    }
}
