<?php
declare(strict_types=1);

namespace Crustum\Viator\Converter;

use Crustum\Scrutator\Component\JsonApiResponse;
use Crustum\Viator\Lang\TypeScript;

/**
 * JSON:API resource responses to TypeScript types.
 *
 * Every resource renders `{ data: {...} }` with the `id`/`type` pair plus
 * whichever of `attributes`, `relationships`, `links`, and `meta` are
 * present (each optional); collections wrap the resource in a list.
 */
final class JsonApiData extends Converter
{
    /**
     * Convert a collected Scrutator JSON:API response.
     *
     * @param \Crustum\Scrutator\Component\JsonApiResponse $response JSON:API response component
     * @return string Type string
     */
    public function convert(JsonApiResponse $response): string
    {
        $parts = ['id: string', 'type: string'];
        foreach (
            [
            'attributes' => $response->attributes,
            'relationships' => $response->relationships,
            'links' => $response->links,
            'meta' => $response->meta,
            ] as $key => $section
        ) {
            if ($section === []) {
                continue;
            }

            $parts[] = TypeScript::quoteKey($key) . '?: ' . $this->inlineObject($section);
        }

        $dataType = '{ ' . implode(', ', $parts) . ' }';
        if ($response->isCollection) {
            return "{ data: {$dataType}[] }";
        }

        return "{ data: {$dataType} }";
    }
}
