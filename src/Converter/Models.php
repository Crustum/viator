<?php
declare(strict_types=1);

namespace Crustum\Viator\Converter;

use Cake\Collection\CollectionInterface;
use Crustum\Scrutator\Component\Entity as EntityComponent;
use Crustum\Viator\Lang\TypeScript;

/**
 * CakePHP entities to TypeScript types.
 *
 * Entities arrive as Scrutator components through the engine walk; each
 * namespace path renders one merged namespace block holding all of its
 * entity types, e.g. `TestApp.Model.Entity` with `User`, `Product`, and
 * `Category`.
 */
final class Models extends Converter
{
    /**
     * Convert collected Scrutator entity components.
     *
     * Components sharing a namespace path merge into one block, so a
     * path is never repeated per entity.
     *
     * @param \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\Entity> $entities Entity components
     * @return array<string, string> Dotted namespace path to merged namespace block
     */
    public function blocks(CollectionInterface $entities): array
    {
        /** @var array<string, array<string, array<string, \Crustum\Investigator\Type\Contract\Type>>> $grouped */
        $grouped = $entities
            ->filter(static fn(mixed $entity): bool => $entity instanceof EntityComponent && $entity->visibleProperties() !== [])
            ->map(fn(EntityComponent $entity): array => [$this->namespacePath($entity), $this->shortName($entity), $entity->visibleProperties()])
            ->reduce(
                static function (array $grouped, array $entry): array {
                    [$namespace, $name, $props] = $entry;
                    $propsByName = [];
                    foreach ($props as $prop => $type) {
                        $propsByName[(string)$prop] = $type;
                    }

                    ksort($propsByName);
                    $grouped[$namespace][$name] = $propsByName;

                    return $grouped;
                },
                [],
            );

        ksort($grouped);
        $blocks = [];
        foreach ($grouped as $path => $types) {
            ksort($types);
            $blocks[$path] = $this->namespaceBlock((string)$path, $types);
        }

        return $blocks;
    }

    /**
     * Read the dotted namespace path for an entity component.
     *
     * @param \Crustum\Scrutator\Component\Entity $entity Entity component
     * @return string Dotted namespace path, empty for the global namespace
     */
    protected function namespacePath(EntityComponent $entity): string
    {
        $fqn = ltrim($entity->name, '\\');
        $position = strrpos($fqn, '\\');

        return str_replace('\\', '.', $position === false ? '' : substr($fqn, 0, $position));
    }

    /**
     * Read the short class name for an entity component.
     *
     * @param \Crustum\Scrutator\Component\Entity $entity Entity component
     * @return string Short name
     */
    protected function shortName(EntityComponent $entity): string
    {
        $fqn = ltrim($entity->name, '\\');
        $position = strrpos($fqn, '\\');

        return $position === false ? $fqn : substr($fqn, $position + 1);
    }

    /**
     * Render one merged namespace block holding every type of the path.
     *
     * Global-namespace entities render bare, without a wrapper.
     *
     * @param string $path Dotted namespace path, empty for the global namespace
     * @param array<string, array<string, \Crustum\Investigator\Type\Contract\Type>> $types Type name to prop map
     * @return string Namespace block
     */
    protected function namespaceBlock(string $path, array $types): string
    {
        $declarations = [];
        foreach ($types as $name => $props) {
            $lines = ["export type {$name} = {"];
            foreach ($props as $prop => $type) {
                $lines[] = '    ' . TypeScript::quoteKey((string)$prop) . ': ' . $this->typeString($type, '    ') . ';';
            }

            $lines[] = '};';
            $declarations[] = implode("\n", $lines);
        }

        $block = implode("\n", $declarations);
        if ($path === '') {
            return $block;
        }

        foreach (array_reverse(explode('.', $path)) as $segment) {
            $block = "export namespace {$segment} {\n" . $this->indent($block) . "\n}";
        }

        return $block;
    }
}
