<?php
declare(strict_types=1);

namespace Crustum\Viator\Converter;

use Cake\Collection\CollectionInterface;
use Crustum\Scrutator\Component\InertiaResponse;
use Crustum\Viator\Lang\TypeScript;

/**
 * Inertia page components to TypeScript types.
 *
 * Pages arrive as Scrutator `InertiaResponse` components (the P4 engine
 * walk feeds route actions through the Response collector, which merges
 * renders into the static component registry; a prop seen in only some
 * renders is optional, a prop with different types across renders is
 * their union). Each page intersects the generated `Inertia.SharedData`
 * base. Paths nest on `/`, `::`, `.`, and `\` separators; leaves and
 * branches may collide (`Items` plus `Items/Edit` render a merging type
 * plus namespace).
 */
final class InertiaData extends Converter
{
    /**
     * Map components to page types.
     *
     * @param \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\InertiaResponse>|array<int, \Crustum\Scrutator\Component\InertiaResponse> $components Page components
     * @return array<string, string> `Inertia.Pages.*` FQN to type string
     */
    public function pageTypes(CollectionInterface|array $components): array
    {
        $pages = [];
        foreach ($components as $component) {
            $pages[$this->fqn($component->component)] = $this->pageType($component);
        }

        ksort($pages);

        return $pages;
    }

    /**
     * Render the nested `Pages` namespace block for mapped page types.
     *
     * @param array<string, string> $pages `Inertia.Pages.*` FQN to type string
     * @return string Namespace block (empty string when no pages exist)
     */
    public function renderPages(array $pages): string
    {
        $tree = [];
        foreach ($pages as $fqn => $type) {
            $segments = explode('.', substr($fqn, strlen('Inertia.Pages.')));
            $segments = array_values(array_filter($segments, static fn(string $segment): bool => $segment !== ''));
            if ($segments === []) {
                continue;
            }

            $tree = $this->nestInto($tree, $segments, $type);
        }

        $lines = ['export namespace Pages {'];
        foreach ($this->renderTree($tree, 1) as $line) {
            $lines[] = $line;
        }

        $lines[] = '};';

        return implode("\n", $lines);
    }

    /**
     * Read the dotted FQN for a component path.
     *
     * @param string $component Component path (`Contacts/Index`, `Authorization::Login`)
     * @return string `Inertia.Pages.*` FQN
     */
    protected function fqn(string $component): string
    {
        $segments = preg_split('#[/.:\\\\]#', str_replace('\\', '/', $component)) ?: [];
        $mapped = [];
        foreach ($segments as $segment) {
            $name = $this->typeName($segment);
            if ($name !== '') {
                $mapped[] = $name;
            }
        }

        return 'Inertia.Pages.' . implode('.', $mapped);
    }

    /**
     * Render one page type string.
     *
     * @param \Crustum\Scrutator\Component\InertiaResponse $response Page component
     * @return string Type string
     */
    protected function pageType(InertiaResponse $response): string
    {
        if ($response->data === []) {
            return 'SharedData';
        }

        $data = $response->data;
        ksort($data);
        $lines = ['SharedData & {'];
        foreach ($data as $prop => $type) {
            $optional = $type->isOptional() ? '?' : '';
            $lines[] = '    ' . TypeScript::quoteKey((string)$prop) . "{$optional}: " . $this->typeString($type, '    ') . ';';
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Insert a page type under path segments without by-reference walking.
     *
     * @param array<string, mixed> $tree Existing tree
     * @param array<int, string> $segments Remaining path segments
     * @param string $type Leaf page type
     * @return array<string, mixed> New tree
     */
    protected function nestInto(array $tree, array $segments, string $type): array
    {
        $segment = array_shift($segments);
        if (!is_string($segment) || $segment === '') {
            return $tree;
        }

        /** @var array<string, mixed> $node */
        $node = isset($tree[$segment]) && is_array($tree[$segment]) ? $tree[$segment] : [];
        if ($segments === []) {
            $node['__type'] = $type;
            $tree[$segment] = $node;

            return $tree;
        }

        $children = [];
        foreach ($node as $key => $value) {
            if ($key !== '__type' && is_array($value)) {
                $children[$key] = $value;
            }
        }

        $children = $this->nestInto($children, $segments, $type);
        foreach ($children as $key => $value) {
            $node[$key] = $value;
        }

        $tree[$segment] = $node;

        return $tree;
    }

    /**
     * Studly-case one path segment for a namespace/type name.
     *
     * @param string $segment Path segment
     * @return string
     */
    protected function typeName(string $segment): string
    {
        $segment = str_replace(['-', '_'], ' ', $segment);
        $segment = str_replace(' ', '', ucwords($segment));
        $segment = (string)preg_replace('/[^A-Za-z0-9_$]/', '', $segment);
        if ($segment === '' || preg_match('/^[0-9]/', $segment)) {
            $segment = $segment === '' ? '' : 'Page' . $segment;
        }

        return $segment;
    }

    /**
     * Render the nested pages tree as type/namespace declarations.
     *
     * @param array<string, mixed> $tree Nested tree
     * @param int $depth Indentation depth
     * @return array<int, string> Lines
     */
    protected function renderTree(array $tree, int $depth): array
    {
        $pad = str_repeat('    ', $depth);
        $lines = [];
        ksort($tree);
        foreach ($tree as $name => $value) {
            if ($name === '__type' || !is_array($value)) {
                continue;
            }

            $type = $value['__type'] ?? null;
            $children = $value;
            unset($children['__type']);

            if (is_string($type)) {
                $lines[] = str_replace("\n", "\n" . $pad, "{$pad}export type {$name} = {$type};");
            }

            if ($children !== []) {
                $lines[] = "{$pad}export namespace {$name} {";
                foreach ($this->renderTree($children, $depth + 1) as $line) {
                    $lines[] = $line;
                }

                $lines[] = "{$pad}};";
            }
        }

        return $lines;
    }
}
