<?php
declare(strict_types=1);

namespace Crustum\Viator\Converter;

use Cake\Collection\CollectionInterface;
use Crustum\Viator\Lang\TypeScript;
use Crustum\Viator\Lang\TypeScript\Declaration;
use Crustum\Viator\Result\Result;

/**
 * Broadcast channels to TypeScript.
 *
 * Channel names from the Scrutator `BroadcastChannel` registry become a
 * `BroadcastChannel` union plus a `BroadcastChannels` const object.
 * Static segments nest; `{param}` segments do not create levels — their
 * params wrap the nearest static segment on the left as an arrow function
 * (`orders` becomes `(orderId) => ...`, `user` becomes
 * `(userId) => ({ notifications: ... })`), mirroring upstream.
 */
final class BroadcastChannels
{
    /**
     * Render `broadcast-channels.ts` for collected channel components.
     *
     * @param \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\BroadcastChannel> $channels Channel components
     * @return \Crustum\Viator\Result\Result|null Null when no channels exist
     */
    public function convert(CollectionInterface $channels): ?Result
    {
        $names = [];
        $tree = [];
        foreach ($channels as $channel) {
            $names[$channel->name] = true;
            $tree = $this->mergeChannel($tree, $channel->name);
        }

        if ($names === []) {
            return null;
        }

        $names = array_keys($names);
        sort($names);

        $lines = [
            Declaration::type('BroadcastChannel', TypeScript::union(array_map(TypeScript::quote(...), $names)))->export()->render(),
            '',
        ];
        $lines[] = Declaration::constant('BroadcastChannels', $this->renderConst($tree, 0) . ' as const')->export()->render();

        return new Result('broadcast-channels.ts', implode("\n", $lines) . "\n");
    }

    /**
     * Merge one channel name into the const tree.
     *
     * Static segments nest; `{param}` segments attach their params to the
     * nearest static segment on the left (a reverse pass, like upstream
     * `toConstData`). The deepest static segment keeps the full channel
     * name for its template. Fully parameterized names contribute no const
     * entry; the union still carries them.
     *
     * @param array<string, mixed> $tree Const tree
     * @return array<string, mixed> Merged tree
     */
    protected function mergeChannel(array $tree, string $name): array
    {
        $parts = explode('.', $name);

        $paramsFor = [];
        $pending = [];
        foreach (array_reverse($parts, true) as $index => $part) {
            if (preg_match('/\{([^\}]+)\}/', $part, $match) !== 1) {
                $paramsFor[$index] = array_reverse($pending);
                $pending = [];
                continue;
            }

            $pending[] = $match[1];
        }

        if ($paramsFor === []) {
            return $tree;
        }

        ksort($paramsFor);
        $node = &$tree;
        $last = array_key_last($paramsFor);
        foreach ($paramsFor as $index => $params) {
            $part = $parts[$index];
            if (!isset($node[$part]) || !is_array($node[$part])) {
                $node[$part] = [];
            }

            $existing = $node[$part]['__meta']['params'] ?? [];
            $node[$part]['__meta']['params'] = array_values(array_unique([...$existing, ...$params]));
            if ($index === $last) {
                $node[$part]['__meta']['template'] ??= $name;
            }

            $node = &$node[$part];
        }

        unset($node);

        return $tree;
    }

    /**
     * Render one const tree level.
     *
     * @param array<string, mixed> $tree Nested tree, nodes carry `__meta`
     * @param int $depth Indentation depth
     * @return string
     */
    protected function renderConst(array $tree, int $depth): string
    {
        $pad = str_repeat('    ', $depth);
        $inner = str_repeat('    ', $depth + 1);
        $lines = ['{'];
        $names = array_filter(
            array_keys($tree),
            static fn($key): bool => $key !== '__meta',
        );
        sort($names);
        foreach ($names as $name) {
            $lines[] = $inner . TypeScript::quoteKey($name) . ': ' . $this->renderNode($tree[$name], $depth + 1) . ',';
        }

        $lines[] = $pad . '}';

        return implode("\n", $lines);
    }

    /**
     * Render one tree node: a template literal for leaves, a (possibly
     * parameterized) nested object for branches.
     *
     * @param mixed $node Tree node
     * @param int $depth Indentation depth
     * @return string
     */
    protected function renderNode(mixed $node, int $depth): string
    {
        $children = [];
        if (is_array($node)) {
            foreach ($node as $key => $child) {
                if ($key !== '__meta' && is_array($child)) {
                    $children[$key] = $child;
                }
            }
        }

        $meta = is_array($node['__meta'] ?? null) ? $node['__meta'] : [];
        $params = array_values(array_unique(array_filter($meta['params'] ?? [], is_string(...))));

        if ($children === []) {
            $template = $this->templateFor((string)($meta['template'] ?? ''));
            if ($params === []) {
                return $template;
            }

            $signature = implode(', ', array_map(static fn(string $param): string => "{$param}: string | number", $params));

            return '(' . $signature . ') => ' . $template;
        }

        $nested = $this->renderConst($children, $depth);
        if ($params === []) {
            return $nested;
        }

        $signature = implode(', ', array_map(static fn(string $param): string => "{$param}: string | number", $params));

        return '(' . $signature . ') => (' . $nested . ')';
    }

    /**
     * Render one channel name as a template literal, turning `{param}`
     * segments into `${param}` placeholders.
     *
     * @param string $name Full channel name
     * @return string
     */
    protected function templateFor(string $name): string
    {
        $template = preg_replace_callback(
            '/\{([^\}]+)\}/',
            static fn(array $match): string => TypeScript::templateString($match[1]),
            TypeScript::escapeTemplateLiteral($name),
        );

        return TypeScript::backtick($template ?? $name);
    }
}
