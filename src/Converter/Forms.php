<?php
declare(strict_types=1);

namespace Crustum\Viator\Converter;

use Cake\Collection\Collection;
use Cake\Collection\CollectionInterface;
use Crustum\Scrutator\Component\Validator as ValidatorComponent;
use Crustum\Scrutator\Validation\EnumRule;
use Crustum\Scrutator\Validation\Rule;
use Crustum\Viator\Lang\TypeScript;
use Crustum\Viator\Validation\Rules;

/**
 * CakePHP `Form` validators to TypeScript types.
 *
 * Forms arrive as Scrutator validator components keyed by form class;
 * forms sharing a namespace path merge into one namespace block, e.g.
 * `TestApp.Form` holding every form type.
 */
final class Forms
{
    /**
     * Methods marking the key as allowed-empty (hence optional).
     *
     * @var array<int, string>
     */
    protected const array ALLOW_EMPTY = [
        'allowemptystring',
        'allowemptyarray',
        'allowemptyfile',
        'allowemptydate',
        'allowemptytime',
        'allowemptydatetime',
    ];

    /**
     * Convert collected Scrutator validators to merged namespace blocks.
     *
     * Forms sharing a namespace path merge into one block, so a namespace
     * is never repeated per form.
     *
     * @param \Cake\Collection\CollectionInterface<class-string, \Crustum\Scrutator\Component\Validator> $validators Validators keyed by form class
     * @return array<string, string> Dotted namespace path to merged namespace block
     */
    public function blocks(CollectionInterface $validators): array
    {
        /** @var array<string, array<string, array{required: bool, kinds: array<int, string>, inList: array<int, string>|null, enum: string|null}>> $grouped */
        $grouped = $validators->reduce(
            function (array $grouped, mixed $validator, mixed $form): array {
                if (!$validator instanceof ValidatorComponent || !is_string($form) || !class_exists($form) || !str_ends_with($form, 'Form')) {
                    return $grouped;
                }

                $grouped[$this->namespacePath($form)][$this->shortName($form)] = $this->specs($validator);

                return $grouped;
            },
            [],
        );

        ksort($grouped);

        /** @var array<string, string> $blocks */
        $blocks = (new Collection($grouped))
            ->map(fn(array $forms, string $path): string => $this->namespaceBlock($path, $forms))
            ->toArray();

        return $blocks;
    }

    /**
     * Read the dotted namespace path for a form class.
     *
     * @param class-string $form Form class name
     * @return string Dotted namespace path, empty for the global namespace
     */
    protected function namespacePath(string $form): string
    {
        $fqn = ltrim($form, '\\');
        $position = strrpos($fqn, '\\');

        return str_replace('\\', '.', $position === false ? '' : substr($fqn, 0, $position));
    }

    /**
     * Read the short class name for a form class.
     *
     * @param class-string $form Form class name
     * @return string Short name
     */
    protected function shortName(string $form): string
    {
        $fqn = ltrim($form, '\\');
        $position = strrpos($fqn, '\\');

        return $position === false ? $fqn : substr($fqn, $position + 1);
    }

    /**
     * Map a collected validator to field specs.
     *
     * `requirePresence` without any `allowEmpty*()` marks the key required;
     * every other rule name becomes a type kind, with `inList` carrying its
     * literal values and `enum` rules carrying the referenced enum class.
     *
     * @param \Crustum\Scrutator\Component\Validator $validator Collected validator
     * @return array<string, array{required: bool, kinds: array<int, string>, inList: array<int, string>|null, enum: string|null}> Field specs
     */
    protected function specs(ValidatorComponent $validator): array
    {
        /** @var array<string, array{required: bool, kinds: array<int, string>, inList: array<int, string>|null, enum: string|null}> $specs */
        $specs = (new Collection($validator->rules))
            ->map(fn(array $fieldRules): array => (new Collection($fieldRules))->reduce($this->applyRule(...), ['required' => false, 'kinds' => [], 'inList' => null, 'enum' => null]))
            ->map(fn(array $spec): array => $spec['required'] && array_intersect($spec['kinds'], self::ALLOW_EMPTY) !== [] ? array_merge($spec, ['required' => false]) : $spec)
            ->toArray();
        ksort($specs);

        return $specs;
    }

    /**
     * Fold one rule into a field spec.
     *
     * @param array{required: bool, kinds: array<int, string>, inList: array<int, string>|null, enum: string|null} $spec Field spec
     * @param \Crustum\Scrutator\Validation\Rule $rule Collected rule
     * @return array{required: bool, kinds: array<int, string>, inList: array<int, string>|null, enum: string|null} Field spec
     */
    protected function applyRule(array $spec, Rule $rule): array
    {
        if ($rule->rule() instanceof EnumRule) {
            $spec['enum'] = $rule->rule()->enum;

            return $spec;
        }

        $name = $rule->rule();
        if (!is_string($name)) {
            $spec['kinds'][] = 'enum';

            return $spec;
        }

        $lower = strtolower($name);
        if ($lower === 'requirepresence') {
            $spec['required'] = true;

            return $spec;
        }

        $spec['kinds'][] = $lower;

        if ($lower === 'inlist') {
            $values = array_values(array_filter($rule->getParams(), is_string(...)));
            if ($values !== []) {
                $spec['inList'] = $values;
            }
        }

        return $spec;
    }

    /**
     * Render one merged namespace block holding every form type of the path.
     *
     * Global-namespace forms render bare, without a wrapper.
     *
     * @param string $path Dotted namespace path, empty for the global namespace
     * @param array<string, array<string, array{required: bool, kinds: array<int, string>, inList: array<int, string>|null, enum: string|null}>> $forms Type name to field specs
     * @return string Namespace block
     */
    protected function namespaceBlock(string $path, array $forms): string
    {
        ksort($forms);
        $block = (new Collection($forms))
            ->map(fn(array $fields, string $name): string => $this->typeBlock($name, $fields))
            ->implode("\n");

        if ($path === '') {
            return $block;
        }

        return (new Collection(array_reverse(explode('.', $path))))
            ->reduce(fn(string $block, string $segment): string => "export namespace {$segment} {\n" . $this->indent($block) . "\n}", $block);
    }

    /**
     * Render one form type declaration.
     *
     * @param string $name Type name
     * @param array<string, array{required: bool, kinds: array<int, string>, inList: array<int, string>|null, enum: string|null}> $fields Field specs
     * @return string Type declaration
     */
    protected function typeBlock(string $name, array $fields): string
    {
        return (new Collection(["export type {$name} = {"]))
            ->append($this->renderFields($this->nestFields($fields), 1))
            ->append(['};'])
            ->implode("\n");
    }

    /**
     * Nest dotted fields into a tree.
     *
     * @param array<string, array{required: bool, kinds: array<int, string>, inList: array<int, string>|null, enum: string|null}> $fields Field specs
     * @return array<string, mixed> Nested fields
     */
    protected function nestFields(array $fields): array
    {
        return (new Collection($fields))
            ->map(fn(array $spec, string $field): array => [explode('.', $field), $spec])
            ->reduce(fn(array $tree, array $pair): array => $this->insertInto($tree, $pair[0], $this->leaf($pair[1])), []);
    }

    /**
     * Insert one leaf at its dotted path.
     *
     * @param array<string, mixed> $tree Nested fields
     * @param array<int, string> $parts Dotted path parts
     * @param array{type: string, required: bool} $leaf Field leaf
     * @return array<string, mixed> Nested fields
     */
    protected function insertInto(array $tree, array $parts, array $leaf): array
    {
        $key = (string)array_shift($parts);
        if ($parts === []) {
            $tree[$key] = $leaf;

            return $tree;
        }

        $branch = isset($tree[$key]) && is_array($tree[$key]) ? $tree[$key] : [];
        $tree[$key] = $this->insertInto($branch, $parts, $leaf);

        return $tree;
    }

    /**
     * Map a field spec to its type leaf.
     *
     * @param array{required: bool, kinds: array<int, string>, inList: array<int, string>|null, enum: string|null} $spec Field spec
     * @return array{type: string, required: bool} Field leaf
     */
    protected function leaf(array $spec): array
    {
        $rules = new Rules($spec['required'], $spec['kinds'], $spec['inList'], $spec['enum']);

        return ['type' => $rules->resolveFieldType(), 'required' => $rules->isRequired()];
    }

    /**
     * Render nested field lines (`?:` for optional keys).
     *
     * @param array<string, mixed> $tree Nested fields
     * @param int $depth Indentation depth
     * @return array<int, string> Lines
     */
    protected function renderFields(array $tree, int $depth): array
    {
        $pad = str_repeat('    ', $depth);
        ksort($tree);

        return (new Collection($tree))
            ->map(fn(mixed $value, mixed $key): array => $this->fieldLines((string)$key, $value, $pad, $depth))
            ->unfold()
            ->toList();
    }

    /**
     * Render lines for one field key.
     *
     * @param string $key Field key
     * @param mixed $value Leaf spec or nested branch
     * @param string $pad Indentation pad
     * @param int $depth Indentation depth
     * @return array<int, string> Lines
     */
    protected function fieldLines(string $key, mixed $value, string $pad, int $depth): array
    {
        $safe = TypeScript::quoteKey($key);
        if (is_array($value) && isset($value['type'])) {
            return ["{$pad}{$safe}" . ($value['required'] ? '' : '?') . ": {$value['type']};"];
        }

        $branch = is_array($value) ? $value : [];
        $required = $this->branchRequired($branch);

        return (new Collection(["{$pad}{$safe}" . ($required ? '' : '?') . ': {']))
            ->append($this->renderFields($branch, $depth + 1))
            ->append(["{$pad}};"])
            ->toList();
    }

    /**
     * A branch is required when anything below it is.
     *
     * @param array<string, mixed> $tree Nested fields
     * @return bool
     */
    protected function branchRequired(array $tree): bool
    {
        return (new Collection($tree))->some(
            fn(mixed $value): bool => is_array($value) && (isset($value['type']) ? (bool)$value['required'] : $this->branchRequired($value)),
        );
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
}
