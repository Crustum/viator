<?php
declare(strict_types=1);

namespace Crustum\Viator\Assembler;

use Cake\Collection\CollectionInterface;
use Crustum\Scrutator\Component\InertiaSharedData as InertiaSharedDataComponent;
use Crustum\Scrutator\Component\ResourceResponse as ResourceResponseComponent;
use Crustum\Viator\Converter\Forms;
use Crustum\Viator\Converter\InertiaData;
use Crustum\Viator\Converter\InertiaSharedData;
use Crustum\Viator\Converter\JsonApiData;
use Crustum\Viator\Converter\ResourceData;
use Crustum\Viator\Lang\TypeScript;
use Crustum\Viator\Lang\TypeScript\Declaration;
use Crustum\Viator\Result\Result;

/**
 * `types.d.ts` assembly from walk data.
 *
 * Sections assemble from the walk: entity blocks, the Inertia block
 * (shared data plus pages), enum roots, form blocks, and per-route
 * response types.
 */
final class TypesAssembler
{
    /**
     * @var array<string, string> Entity namespace blocks keyed by dotted FQN path
     */
    protected array $modelBlocks = [];

    /**
     * @var array<string, string> Enum unions keyed by dotted FQN path
     */
    protected array $enumUnions = [];

    /**
     * @var array<string, array{class: string, file: string|null}> Enum class references keyed by dotted FQN path
     */
    protected array $enumDocs = [];

    /**
     * @var array<int, \Crustum\Scrutator\Component\InertiaSharedData> Shared-data components from the walk
     */
    protected array $sharedData = [];

    /**
     * @var array<int, \Crustum\Scrutator\Component\InertiaResponse> Page components from scanned actions
     */
    protected array $inertiaPages = [];

    /**
     * @var array<int, array{controller: string, action: string, response: \Crustum\Scrutator\Component\ResourceResponse|\Crustum\Scrutator\Component\JsonApiResponse}> Response payloads keyed by controller action
     */
    protected array $routeResponses = [];

    /**
     * @var array<class-string, \Crustum\Scrutator\Component\Validator> Form validators keyed by form class
     */
    protected array $formValidators = [];

    /**
     * @param bool $skipEnums Whether enum sections stay out
     * @param bool $skipForms Whether form sections stay out
     * @param string $runtimeDir Output subdirectory for the runtime
     */
    public function __construct(
        protected bool $skipEnums = false,
        protected bool $skipForms = false,
        protected string $runtimeDir = 'viator',
    ) {
    }

    /**
     * Set the entity namespace blocks.
     *
     * @param array<string, string> $modelBlocks Dotted FQN path to merged namespace block
     * @return void
     */
    public function setModels(array $modelBlocks): void
    {
        $this->modelBlocks = $modelBlocks;
    }

    /**
     * Set the enum unions with their class references.
     *
     * @param array<string, string> $unions Dotted FQN path to union
     * @param array<string, array{class: string, file: string|null}> $docs Class references keyed by dotted FQN path
     * @return void
     */
    public function setEnums(array $unions, array $docs): void
    {
        $this->enumUnions = $unions;
        $this->enumDocs = $docs;
    }

    /**
     * Set the form validators.
     *
     * @param \Cake\Collection\CollectionInterface<class-string, \Crustum\Scrutator\Component\Validator> $validators Validators keyed by form class
     * @return void
     */
    public function setValidators(CollectionInterface $validators): void
    {
        /** @var array<class-string, \Crustum\Scrutator\Component\Validator> $mapped */
        $mapped = $validators->toArray();

        $this->formValidators = $mapped;
    }

    /**
     * Set the shared-data components.
     *
     * @param array<int, \Crustum\Scrutator\Component\InertiaSharedData> $sharedData Shared-data components
     * @return void
     */
    public function setSharedData(array $sharedData): void
    {
        $this->sharedData = $sharedData;
    }

    /**
     * Add one shared-data component.
     *
     * @param \Crustum\Scrutator\Component\InertiaSharedData $shared Shared-data component
     * @return void
     */
    public function addSharedData(InertiaSharedDataComponent $shared): void
    {
        $this->sharedData[] = $shared;
    }

    /**
     * Set the page components.
     *
     * @param array<int, \Crustum\Scrutator\Component\InertiaResponse> $pages Page components
     * @return void
     */
    public function setPages(array $pages): void
    {
        $this->inertiaPages = $pages;
    }

    /**
     * Set the response payloads.
     *
     * @param array<int, array{controller: string, action: string, response: \Crustum\Scrutator\Component\ResourceResponse|\Crustum\Scrutator\Component\JsonApiResponse}> $responses Response payloads
     * @return void
     */
    public function setResponses(array $responses): void
    {
        $this->routeResponses = $responses;
    }

    /**
     * Assemble the `App` / `Inertia` TypeScript namespaces for the runtime dir.
     *
     * @return \Crustum\Viator\Result\Result
     */
    public function assemble(): Result
    {
        $out = [];
        foreach ($this->modelBlocks as $block) {
            $out[] = $block;
        }

        $out[] = $this->inertiaBlock();

        if (!$this->skipEnums) {
            foreach ($this->renderEnumRoots($this->enumUnions) as $line) {
                $out[] = $line;
            }
        }

        if (!$this->skipForms) {
            foreach ((new Forms())->blocks(collection($this->formValidators)) as $block) {
                $out[] = $block;
            }
        }

        foreach ($this->responseBlocks() as $block) {
            $out[] = $block;
        }

        return new Result($this->runtimeDir . '/types.d.ts', implode("\n", $out) . "\n");
    }

    /**
     * Render the `Inertia` namespace block from the walk.
     *
     * @return string
     */
    protected function inertiaBlock(): string
    {
        $shared = null;
        foreach ($this->sharedData as $component) {
            if ($component->data->value !== []) {
                $shared = $component;
                break;
            }
        }

        $body = "{\n        [key: string]: unknown;\n    }";
        if ($shared instanceof InertiaSharedDataComponent) {
            $blocks = (new InertiaSharedData())->sharedData(collection([$shared]));
            if (isset($blocks['Inertia.SharedData'])) {
                $body = '    ' . str_replace("\n", "\n    ", $blocks['Inertia.SharedData']);
            }
        }

        $lines = ['export namespace Inertia {'];
        $lines[] = '    export type SharedData = ' . $body . ';';

        $components = [];
        foreach ($this->inertiaPages as $response) {
            $components[] = $response;
        }

        $inertia = new InertiaData();
        if ($inertia->pageTypes($components) !== []) {
            $block = $inertia->renderPages($inertia->pageTypes($components));
            $lines[] = '    ' . str_replace("\n", "\n    ", $block);
        }

        $lines[] = '};';

        return implode("\n", $lines);
    }

    /**
     * Render per-route response type blocks from the walk.
     *
     * One `Response` union per unique controller and action, mirroring the
     * upstream `[controller, Method, Response]` nesting.
     *
     * @return array<int, string>
     */
    protected function responseBlocks(): array
    {
        $grouped = [];
        foreach ($this->routeResponses as $entry) {
            $response = $entry['response'];
            if ($response instanceof ResourceResponseComponent) {
                $type = (new ResourceData())->convert($response);
            } else {
                $type = (new JsonApiData())->convert($response);
            }

            $key = $entry['controller'] . ':' . $entry['action'];
            $grouped[$key] ??= ['controller' => $entry['controller'], 'action' => $entry['action'], 'types' => []];

            if (!in_array($type, $grouped[$key]['types'], true)) {
                $grouped[$key]['types'][] = $type;
            }
        }

        ksort($grouped);
        $blocks = [];
        foreach ($grouped as $entry) {
            $segments = explode('.', $entry['controller']);
            $segments[] = ucwords($entry['action']);
            $segments[] = 'Response';

            $leaf = 'export type ' . array_pop($segments) . ' = ' . implode(' | ', $entry['types']) . ';';
            $block = $leaf;
            foreach (array_reverse($segments) as $segment) {
                $block = "export namespace {$segment} {\n" . $this->indent($block) . "\n}";
            }

            $blocks[] = $block;
        }

        return $blocks;
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
     * Render enum unions grouped by root segment.
     *
     * Roots merge with earlier `export namespace` blocks of the same name,
     * so `App.Enum.*` augments the `App` block above.
     *
     * @param array<string, string> $enums Dotted path to union
     * @return array<int, string> Lines
     */
    protected function renderEnumRoots(array $enums): array
    {
        if ($enums === []) {
            return [];
        }

        $roots = collection(array_keys($enums))->reduce(
            fn(array $tree, string $path): array => TypeScript::nestValue($tree, explode('.', $path), $enums[$path]),
            [],
        );

        $lines = [];
        ksort($roots);
        foreach ($roots as $root => $tree) {
            $lines[] = "export namespace {$root} {";
            foreach ($this->renderEnumTree($tree, 1, (string)$root) as $line) {
                $lines[] = $line;
            }

            $lines[] = '}';
        }

        return $lines;
    }

    /**
     * Render one enum tree level.
     *
     * @param array<string, mixed> $tree Nested tree, string leaves are unions
     * @param int $depth Indentation depth
     * @param string $prefix Dotted path of the current level
     * @return array<int, string> Lines
     */
    protected function renderEnumTree(array $tree, int $depth, string $prefix): array
    {
        $pad = str_repeat('    ', $depth);
        $lines = [];
        ksort($tree);
        foreach ($tree as $name => $value) {
            if (is_string($value)) {
                $declaration = Declaration::type($name, $value)->export();
                $doc = $this->enumDocs[$prefix . '.' . $name] ?? null;
                if (is_array($doc)) {
                    $declaration->referenceClass($doc['class'], $doc['file']);
                }

                $rendered = str_replace(["\r\n", "\r"], "\n", $declaration->render());
                $lines[] = $pad . str_replace("\n", "\n" . $pad, $rendered);
                continue;
            }

            $lines[] = "{$pad}export namespace {$name} {";
            foreach ($this->renderEnumTree($value, $depth + 1, $prefix . '.' . $name) as $line) {
                $lines[] = $line;
            }

            $lines[] = "{$pad}};";
        }

        return $lines;
    }
}
