<?php
declare(strict_types=1);

namespace Crustum\Viator\Converter;

use Cake\Collection\CollectionInterface;
use Crustum\Investigator\Type\ArrayType;
use Crustum\Investigator\Type\Contract\Type as TypeContract;
use Crustum\Investigator\Type\UnionType;
use Crustum\Scrutator\Component\BroadcastEvent as BroadcastEventComponent;
use Crustum\Viator\Lang\TypeScript;
use Crustum\Viator\Lang\TypeScript\Declaration;
use Crustum\Viator\Registry\TypeScriptConverter;
use Crustum\Viator\Result\Result;
use Crustum\Viator\Support\Npm;

/**
 * Broadcastable events to TypeScript.
 *
 * Events implementing the broadcasting contract are discovered through
 * the Scrutator collector; names stay verbatim wire names (FQCN with
 * backslashes for `static::class`, string literals as-is), and `echo`
 * prefixes the name with `.` — the Echo convention for a fully-qualified
 * name used as-is (`.listen('.Namespace\Event\Class')`). Payload shapes
 * render through the P2 type converter.
 *
 * Emits `broadcast-events.ts` plus, when an Echo package is installed,
 * `echo-broadcast-events.d.ts` with a typed global `Events` interface.
 */
final class BroadcastEvents
{
    /**
     * @param string|null $echoPackage Installed Echo package (`@laravel/echo-vue`), null to skip the d.ts
     */
    public function __construct(
        protected ?string $echoPackage = null,
    ) {
    }

    /**
     * @var \Crustum\Viator\Registry\TypeScriptConverter|null
     */
    protected ?TypeScriptConverter $converter = null;

    /**
     * Read the type converter.
     *
     * @return \Crustum\Viator\Registry\TypeScriptConverter
     */
    protected function typeConverter(): TypeScriptConverter
    {
        $this->converter ??= new TypeScriptConverter();

        return $this->converter;
    }

    /**
     * Map a collected event to its payload shape.
     *
     * @param \Crustum\Scrutator\Component\BroadcastEvent $event Collected event
     * @return array<string, string> Prop name to TS type
     */
    protected function payload(BroadcastEventComponent $event): array
    {
        $data = $event->data;
        if ($data instanceof UnionType) {
            foreach ($data->getTypes() as $member) {
                if ($member instanceof ArrayType) {
                    $data = $member;
                    break;
                }
            }
        }

        if (!$data instanceof ArrayType) {
            return [];
        }

        $payload = [];
        foreach ($data->value as $prop => $type) {
            $payload[(string)$prop] = $type instanceof TypeContract
                ? $this->typeConverter()->convert($type)
                : 'unknown';
        }

        ksort($payload);

        return $payload;
    }

    /**
     * Convert collected Scrutator event components.
     *
     * The engine walk hands components straight to this entry point: they
     * map to shapes through {@see shape()}, then render like discovered
     * events.
     *
     * @param \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\BroadcastEvent> $components Event components
     * @param string|null $baseDir Base dir for the Echo package lookup (host root)
     * @return array<int, \Crustum\Viator\Result\Result>
     */
    public function convertComponents(CollectionInterface $components, ?string $baseDir = null): array
    {
        $events = [];
        foreach ($components as $component) {
            $events[] = $this->shape($component);
        }

        usort($events, static fn(array $a, array $b): int => $a['echo'] <=> $b['echo']);

        return $this->render($events, $baseDir);
    }

    /**
     * Map a collected event component to its array shape.
     *
     * @param \Crustum\Scrutator\Component\BroadcastEvent $event Collected event
     * @return array{name: string, echo: string, payload: array<string, string>, class: string, file: string} Event shape
     */
    protected function shape(BroadcastEventComponent $event): array
    {
        return [
            'name' => $event->name,
            'echo' => '.' . $event->name,
            'payload' => $this->payload($event),
            'class' => $event->className,
            'file' => $event->filePath(),
        ];
    }

    /**
     * Render results for event shapes.
     *
     * @param array<int, array{name: string, echo: string, payload: array<string, string>, class: string, file: string}> $events Event shapes
     * @param string|null $baseDir Base dir for the Echo package lookup (host root)
     * @return array<int, \Crustum\Viator\Result\Result>
     */
    protected function render(array $events, ?string $baseDir = null): array
    {
        if ($events === []) {
            return [];
        }

        $echoPackage = $this->echoPackage ?? Npm::findFirstInstalledPackage(
            ['@laravel/echo-vue', '@laravel/echo-react'],
            $baseDir,
        );

        $results = [new Result('broadcast-events.ts', $this->eventsFile($events))];
        if ($echoPackage !== null) {
            $results[] = new Result('echo-broadcast-events.d.ts', $this->echoFile($events));
        }

        return $results;
    }

    /**
     * Render `broadcast-events.ts`.
     *
     * @param array<int, array{name: string, echo: string, payload: array<string, string>, class: string, file: string}> $events
     * @return string
     */
    protected function eventsFile(array $events): string
    {
        $names = array_map(static fn(array $event): string => $event['echo'], $events);
        $lines = [Declaration::type('BroadcastEvent', TypeScript::union(array_map(TypeScript::quote(...), $names)))->export()->render(), ''];

        $tree = [];
        foreach ($events as $event) {
            $segments = explode('.', str_replace('\\', '.', ltrim($event['name'], '\\')));
            $leaf = array_pop($segments);
            if ($leaf === '') {
                continue;
            }

            $tree = TypeScript::nestValue($tree, [...$segments, $leaf], TypeScript::quote($event['echo']));
        }

        $lines[] = Declaration::constant('BroadcastEvents', $this->renderConst($tree, 0) . ' as const')->export()->render();

        return implode("\n", $lines) . "\n";
    }

    /**
     * Render a nested const object.
     *
     * @param array<string, mixed> $tree Nested tree, string leaves
     * @param int $depth Indentation depth
     * @return string
     */
    protected function renderConst(array $tree, int $depth): string
    {
        $pad = str_repeat('    ', $depth);
        $inner = str_repeat('    ', $depth + 1);
        $lines = ['{'];
        ksort($tree);
        foreach ($tree as $key => $value) {
            $safe = TypeScript::quoteKey($key);
            if (is_string($value)) {
                $lines[] = "{$inner}{$safe}: {$value},";
                continue;
            }

            $lines[] = "{$inner}{$safe}: " . $this->renderConst($value, $depth + 1) . ',';
        }

        $lines[] = $pad . '}';

        return implode("\n", $lines);
    }

    /**
     * Render `echo-broadcast-events.d.ts`.
     *
     * @param array<int, array{name: string, echo: string, payload: array<string, string>, class: string, file: string}> $events
     * @return string
     */
    protected function echoFile(array $events): string
    {
        $lines = [];
        $needsApp = false;
        foreach ($events as $event) {
            foreach ($event['payload'] as $ts) {
                if (str_contains($ts, 'App.')) {
                    $needsApp = true;
                    break 2;
                }
            }
        }

        if ($needsApp) {
            $lines[] = "import type { App } from './types';";
            $lines[] = '';
        }

        $lines[] = 'declare global {';
        $lines[] = '    interface Events {';
        foreach ($events as $event) {
            $props = [];
            foreach ($event['payload'] as $prop => $ts) {
                $props[] = '            ' . TypeScript::quoteKey($prop) . ': ' . $ts . ';';
            }

            $shape = $props === [] ? '{}' : "{\n" . implode("\n", $props) . "\n        }";
            $lines[] = '        ' . TypeScript::quote($event['echo']) . ': ' . $shape . ';';
        }

        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'export {};';

        return implode("\n", $lines) . "\n";
    }
}
