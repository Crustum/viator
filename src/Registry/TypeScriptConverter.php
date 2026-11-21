<?php
declare(strict_types=1);

namespace Crustum\Viator\Registry;

use Cake\Collection\CollectionInterface;
use Crustum\Investigator\Analyzer\ArrayableResolver;
use Crustum\Investigator\Type\ArrayShapeType;
use Crustum\Investigator\Type\ArrayType;
use Crustum\Investigator\Type\BoolType;
use Crustum\Investigator\Type\CallableType;
use Crustum\Investigator\Type\ClassType;
use Crustum\Investigator\Type\Contract\Type;
use Crustum\Investigator\Type\Entity\ResourceResponse;
use Crustum\Investigator\Type\FloatType;
use Crustum\Investigator\Type\IntersectionType;
use Crustum\Investigator\Type\IntType;
use Crustum\Investigator\Type\MixedType;
use Crustum\Investigator\Type\NullType;
use Crustum\Investigator\Type\NumberType;
use Crustum\Investigator\Type\StringType;
use Crustum\Investigator\Type\UnionType;
use Crustum\Viator\Lang\TypeScript;
use DateTimeInterface;
use InvalidArgumentException;
use Stringable;

/**
 * Converts analyzed Investigator types to TypeScript.
 *
 * Shape rendering is inline on purpose: the object-builder tree stays
 * unported (Twig owns declaration emit; see `docs/FILE_MAPPING.md`), so the
 * converter draws `{ k: v }` literals itself instead of building them.
 */
class TypeScriptConverter extends AbstractConverter
{
    /**
     * @param \Crustum\Investigator\Analyzer\ArrayableResolver|null $arrayable Entity shape resolver, if available
     */
    public function __construct(protected ?ArrayableResolver $arrayable = null)
    {
    }

    /**
     * Convert an analyzed type.
     *
     * @param \Crustum\Investigator\Type\Contract\Type $result Analyzed type
     * @return string TypeScript type
     */
    public function convert(Type $result): string
    {
        return match ($result::class) {
            ArrayType::class => $this->convertArrayResult($result),
            ArrayShapeType::class => $this->convertArrayShapeResult($result),
            BoolType::class => $this->convertBoolResult($result),
            ClassType::class => $this->convertClassResult($result),
            ResourceResponse::class => $this->convertResourceResponse($result),
            IntType::class, FloatType::class, NumberType::class => $this->convertNumberResult($result),
            IntersectionType::class => $this->convertIntersectionResult($result),
            MixedType::class => $this->convertMixedResult(),
            NullType::class => $this->convertNullResult(),
            StringType::class => $this->convertStringResult($result),
            UnionType::class => $this->convertUnionResult($result),
            CallableType::class => $this->convertCallableResult($result),
            default => throw new InvalidArgumentException('Unsupported result type: ' . $result::class),
        };
    }

    /**
     * Convert a resource response to its data shape.
     *
     * Inertia/array contexts call `toArray()` directly without the JSON wrap,
     * so only the data shape is emitted.
     *
     * @param \Crustum\Investigator\Type\Entity\ResourceResponse $result Resource response
     * @return string TypeScript type
     */
    protected function convertResourceResponse(ResourceResponse $result): string
    {
        $shape = $result->data instanceof ArrayType
            ? $this->typeObject($result->data->value)
            : $this->convert($result->data);

        return $result->isCollection ? "{$shape}[]" : $shape;
    }

    /**
     * Convert a callable to its return type.
     *
     * @param \Crustum\Investigator\Type\CallableType $result Callable type
     * @return string TypeScript type
     */
    protected function convertCallableResult(CallableType $result): string
    {
        return $this->convert($result->returnType);
    }

    /**
     * Convert an array type.
     *
     * @param \Crustum\Investigator\Type\ArrayType $result Array type
     * @return string TypeScript type
     */
    protected function convertArrayResult(ArrayType $result): string
    {
        $value = $result->value;

        $nullSuffix = $result->isNullable() ? ' | null' : '';

        if (array_is_list($value)) {
            if ($value === []) {
                return 'unknown[]' . $nullSuffix;
            }

            /** @var \Cake\Collection\CollectionInterface<array-key, string> $mapped */
            $mapped = collection($value)->map(fn(mixed $item): string => $this->getType($item));

            $types = TypeScript::union($mapped->toList());

            if (str_contains($types, '|')) {
                return '(' . $types . ')[]' . $nullSuffix;
            }

            return $types . '[]' . $nullSuffix;
        }

        return $this->recordObject($value) . $nullSuffix;
    }

    /**
     * Convert an array shape to a record or a list.
     *
     * @param \Crustum\Investigator\Type\ArrayShapeType $result Array shape
     * @return string TypeScript type
     */
    protected function convertArrayShapeResult(ArrayShapeType $result): string
    {
        $keyType = $this->convert($result->keyType);
        $valueType = $this->convert($result->valueType);

        if ($keyType === 'number') {
            return "{$valueType}[]";
        }

        if ($keyType === 'unknown') {
            $keyType = 'string';
        }

        return "Record<{$keyType}, {$valueType}>";
    }

    /**
     * Convert a boolean, keeping a literal value when known.
     *
     * @param \Crustum\Investigator\Type\BoolType $result Boolean type
     * @return string TypeScript type
     */
    protected function convertBoolResult(BoolType $result): string
    {
        $value = 'boolean';

        if ($result->value !== null) {
            $value = $result->value ? 'true' : 'false';
        }

        return $this->decorate($value, $result);
    }

    /**
     * Convert a class reference.
     *
     * Stringables and dates become `string`, Cake collections become lists of
     * their element type, arrayable entities resolve to their serialized
     * shape, and anything else mirrors its namespace with dots.
     *
     * @param \Crustum\Investigator\Type\ClassType $result Class type
     * @return string TypeScript type
     */
    protected function convertClassResult(ClassType $result): string
    {
        $class = ltrim($result->value, '\\');

        $matched = match (true) {
            $class === Stringable::class => 'string',
            is_a($class, DateTimeInterface::class, true) => 'string',
            is_a($class, CollectionInterface::class, true) => $this->convertCollectionType($result),
            default => null,
        };

        if ($matched === null) {
            if ($this->arrayable instanceof ArrayableResolver) {
                $resolved = $this->arrayable->resolve($result);

                if ($resolved instanceof Type) {
                    return $this->convert($resolved);
                }
            }

            $matched = str_replace('\\', '.', $class);
        }

        return $this->decorate($matched, $result);
    }

    /**
     * Convert a Cake collection to the list of its element type.
     *
     * @param \Crustum\Investigator\Type\ClassType $result Collection type
     * @return string TypeScript type
     */
    protected function convertCollectionType(ClassType $result): string
    {
        $genericTypes = array_values($result->genericTypes());

        return match (count($genericTypes)) {
            0 => 'unknown[]',
            1 => $this->convert($genericTypes[0]) . '[]',
            default => $this->convert($genericTypes[1]) . '[]',
        };
    }

    /**
     * Convert a numeric type.
     *
     * @param \Crustum\Investigator\Type\IntType|\Crustum\Investigator\Type\FloatType|\Crustum\Investigator\Type\NumberType $result Numeric type
     * @return string TypeScript type
     */
    protected function convertNumberResult(IntType|FloatType|NumberType $result): string
    {
        return $this->decorate('number', $result);
    }

    /**
     * Append the nullable suffix when the type allows null.
     *
     * @param string $type Rendered type
     * @param \Crustum\Investigator\Type\Contract\Type $result Analyzed type
     * @return string TypeScript type
     */
    protected function decorate(string $type, Type $result): string
    {
        if ($result->isNullable()) {
            $type .= ' | null';
        }

        return $type;
    }

    /**
     * Convert a union type.
     *
     * @param \Crustum\Investigator\Type\UnionType $result Union type
     * @return string TypeScript type
     */
    protected function convertUnionResult(UnionType $result): string
    {
        return $this->convertUnionOrIntersection($result->getTypes(), '|');
    }

    /**
     * Convert an intersection type.
     *
     * @param \Crustum\Investigator\Type\IntersectionType $result Intersection type
     * @return string TypeScript type
     */
    protected function convertIntersectionResult(IntersectionType $result): string
    {
        return $this->convertUnionOrIntersection($result->getTypes(), '&');
    }

    /**
     * Join member types, dropping a lone `unknown` beside real types.
     *
     * @param array<array-key, mixed> $types Member types
     * @param string $glue Union or intersection glue
     * @return string TypeScript type
     */
    protected function convertUnionOrIntersection(array $types, string $glue): string
    {
        /** @var \Cake\Collection\CollectionInterface<array-key, string|null> $converted */
        $converted = collection($types)
            ->map(function (mixed $item): ?string {
                if (is_array($item)) {
                    /** @var \Cake\Collection\CollectionInterface<array-key, string|null> $nested */
                    $nested = collection($item)
                        ->filter()
                        ->map(fn(mixed $inner): ?string => $inner instanceof Type ? $this->convert($inner) : null)
                        ->unique();

                    $joined = $nested->filter()->implode(' | ');

                    return $joined === '' ? null : $joined;
                }

                if ($item === null) {
                    return null;
                }

                return $item instanceof Type ? $this->convert($item) : null;
            })
            ->filter()
            ->unique();

        /** @var \Cake\Collection\CollectionInterface<array-key, string> $result */
        $result = $converted->filter(fn(?string $type): bool => $type !== null)->unique();

        if ($result->count() > 1 && $result->contains(fn(string $type): bool => $type === 'unknown')) {
            /** @var \Cake\Collection\CollectionInterface<array-key, string> $withoutUnknown */
            $withoutUnknown = $result->filter(fn(string $type): bool => $type !== 'unknown');

            if ($withoutUnknown->count() === 1 && $withoutUnknown->first() === 'null') {
                return 'unknown';
            }

            $result = $withoutUnknown;
        }

        return $result->implode(' ' . $glue . ' ');
    }

    /**
     * Convert a mixed type.
     *
     * @return string TypeScript type
     */
    protected function convertMixedResult(): string
    {
        return 'unknown';
    }

    /**
     * Convert a null type.
     *
     * @return string TypeScript type
     */
    protected function convertNullResult(): string
    {
        return 'null';
    }

    /**
     * Convert a string type.
     *
     * @param \Crustum\Investigator\Type\StringType $result String type
     * @return string TypeScript type
     */
    protected function convertStringResult(StringType $result): string
    {
        return $this->decorate('string', $result);
    }

    /**
     * Read the TypeScript type for a value that may already be rendered.
     *
     * @param mixed $type Analyzed type or rendered string
     * @return string TypeScript type
     */
    protected function getType(mixed $type): string
    {
        if ($type instanceof Type) {
            return $this->convert($type);
        }

        return $type;
    }

    /**
     * Render array values as a multiline object literal.
     *
     * Newlines and indentation follow the object builder the emit layer
     * replaces, so converter output stays byte-identical to upstream.
     *
     * @param array<array-key, mixed> $values Values by key
     * @return string TypeScript object literal
     */
    protected function recordObject(array $values): string
    {
        if ($values === []) {
            return 'Record<string, never>';
        }

        $pairs = [];

        foreach ($values as $key => $value) {
            $pairs[] = TypeScript::indent($this->pair((string)$key, $value));
        }

        return '{' . PHP_EOL . implode(',' . PHP_EOL, $pairs) . ',' . PHP_EOL . '}';
    }

    /**
     * Render array values as an inline type literal.
     *
     * @param array<array-key, mixed> $values Values by key
     * @return string TypeScript type literal
     */
    protected function typeObject(array $values): string
    {
        if ($values === []) {
            return 'Record<string, never>';
        }

        $pairs = [];

        foreach ($values as $key => $value) {
            $pairs[] = $this->pair((string)$key, $value);
        }

        return '{ ' . implode(', ', $pairs) . ' }';
    }

    /**
     * Render one `key: type` pair, marking optional values with `?:`.
     *
     * @param string $key Pair key
     * @param mixed $value Pair value
     * @return string TypeScript pair
     */
    protected function pair(string $key, mixed $value): string
    {
        $optional = $value instanceof Type && $value->isOptional();
        $rendered = $value instanceof Type ? $this->convert($value) : $value;

        if (is_array($rendered)) {
            $rendered = $this->typeObject($rendered);
        }

        $marker = $optional ? '?: ' : ': ';

        return TypeScript::quoteKey($key) . $marker . $rendered;
    }
}
