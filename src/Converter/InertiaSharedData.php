<?php
declare(strict_types=1);

namespace Crustum\Viator\Converter;

use Cake\Collection\CollectionInterface;
use Crustum\Investigator\Type\Contract\Type as TypeContract;
use Crustum\Scrutator\Component\InertiaSharedData as SharedDataComponent;
use Crustum\Viator\Lang\TypeScript;

/**
 * Inertia shared data to a TypeScript type.
 *
 * Shared props are discovered through the Scrutator layer (its collector
 * reads middleware `share()` methods through the Investigator); the exact
 * analyzed shape renders as `SharedData`. An empty shape keeps the loose
 * shell (`{ [key: string]: unknown }`) so apps without shared middleware
 * still compile.
 */
final class InertiaSharedData extends Converter
{
    /**
     * Convert collected Scrutator shared data components.
     *
     * @param \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\InertiaSharedData> $shared Shared data components
     * @return array<string, string> `Inertia.SharedData` FQN to type string
     */
    public function sharedData(CollectionInterface $shared): array
    {
        $found = [];
        foreach ($shared as $component) {
            $found['Inertia.SharedData'] = $this->block($component);
        }

        return $found;
    }

    /**
     * Render the `SharedData` type body.
     *
     * @param \Crustum\Scrutator\Component\InertiaSharedData $component Shared data component
     * @return string Type body
     */
    protected function block(SharedDataComponent $component): string
    {
        $data = $component->data->value;
        if ($data === []) {
            return "{\n    [key: string]: unknown;\n}";
        }

        ksort($data);
        $lines = ['{'];
        foreach ($data as $prop => $type) {
            $optional = $type instanceof TypeContract && $type->isOptional() ? '?' : '';
            $ts = $type instanceof TypeContract ? $this->typeString($type, '    ') : 'unknown';
            $lines[] = '    ' . TypeScript::quoteKey((string)$prop) . "{$optional}: {$ts};";
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }
}
