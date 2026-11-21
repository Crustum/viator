<?php
declare(strict_types=1);

namespace Crustum\Viator\Assembler;

use Cake\Collection\CollectionInterface;
use Crustum\Scrutator\Component\Enum as EnumComponent;
use Crustum\Viator\Converter\Enums;
use Crustum\Viator\Result\Result;
use Throwable;

/**
 * Enum data preparation for the walk.
 *
 * Collects enum unions and per-enum file results from Scrutator
 * components; the command flushes the results and prunes stale files.
 */
final class EnumAssembler
{
    /**
     * @var array<string, string> Dotted FQN path to TS union
     */
    protected array $unions = [];

    /**
     * @var array<string, array{class: string, file: string|null}> Class references keyed by dotted FQN path
     */
    protected array $docs = [];

    /**
     * @var array<int, \Crustum\Viator\Result\Result> Per-enum file results
     */
    protected array $results = [];

    /**
     * @param bool $withMethods Whether enum method values resolve into `NameMeta` constants
     */
    public function __construct(
        protected bool $withMethods = false,
    ) {
    }

    /**
     * Register collected enum components.
     *
     * @param \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\Enum> $enums Enum components
     * @return void
     */
    public function register(CollectionInterface $enums): void
    {
        $converter = new Enums();
        $this->unions = $converter->unions($enums);
        /** @var \Crustum\Scrutator\Component\Enum $enum */
        foreach ($enums as $enum) {
            $this->docs[$this->enumPath($enum)] = [
                'class' => $enum->name,
                'file' => $this->enumFile($enum),
            ];
        }
    }

    /**
     * Convert one collected enum to its file result.
     *
     * @param \Crustum\Scrutator\Component\Enum $enum Enum component
     * @return void
     */
    public function add(EnumComponent $enum): void
    {
        $this->results[] = (new Enums())->withMethods($this->withMethods)->convert($enum);
    }

    /**
     * Read the dotted FQN path to union map.
     *
     * @return array<string, string> Dotted FQN path to TS union
     */
    public function unions(): array
    {
        return $this->unions;
    }

    /**
     * Read the class references keyed by dotted FQN path.
     *
     * @return array<string, array{class: string, file: string|null}> Class references
     */
    public function docs(): array
    {
        return $this->docs;
    }

    /**
     * Read the per-enum file results.
     *
     * @return array<int, \Crustum\Viator\Result\Result> File results
     */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * Read the top-level output roots of the per-enum files.
     *
     * @return array<int, string> First path segments
     */
    public function roots(): array
    {
        /** @var array<int, string> $roots */
        $roots = collection($this->results)
            ->map(static fn(Result $result): string => strstr($result->name, '/', true) ?: $result->name)
            ->filter(static fn(string $root): bool => $root !== '')
            ->unique()
            ->toList();

        return array_values($roots);
    }

    /**
     * Read the dotted FQN path for an enum component.
     *
     * @param \Crustum\Scrutator\Component\Enum $enum Enum component
     * @return string Dotted FQN path
     */
    protected function enumPath(EnumComponent $enum): string
    {
        return str_replace('\\', '.', ltrim($enum->name, '\\'));
    }

    /**
     * Read the component file path, if the collector set one.
     *
     * @param \Crustum\Scrutator\Component\Enum $enum Enum component
     * @return string|null File path
     */
    protected function enumFile(EnumComponent $enum): ?string
    {
        try {
            $file = $enum->filePath();
        } catch (Throwable) {
            return null;
        }

        return $file === '' ? null : $file;
    }
}
