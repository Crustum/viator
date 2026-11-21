<?php
declare(strict_types=1);

namespace Crustum\Viator\Converter;

use Crustum\Scrutator\Component\ResourceResponse;
use Crustum\Viator\Lang\TypeScript;

/**
 * Resource responses to TypeScript types.
 *
 * Collections wrap the item type in a list (unions are grouped first, so
 * `A | B` collects as `(A | B)[]`); an unwrapped resource with extra
 * top-level keys nests under `data`, because that is where the payload
 * lands at runtime.
 */
final class ResourceData extends Converter
{
    /**
     * Convert a collected Scrutator resource response.
     *
     * @param \Crustum\Scrutator\Component\ResourceResponse $response Resource response component
     * @return string Type string
     */
    public function convert(ResourceResponse $response): string
    {
        $dataType = $this->inlineMixedToTs($response->data);

        if ($response->isCollection) {
            $dataType = $this->group($dataType) . '[]';
        }

        $wrap = $response->wrap ?? ($response->additional === [] ? null : 'data');
        if ($wrap === null) {
            return $dataType;
        }

        $parts = [TypeScript::quoteKey($wrap) . ": {$dataType}"];
        $additional = $response->additional;
        ksort($additional);
        foreach ($additional as $key => $value) {
            $parts[] = TypeScript::quoteKey((string)$key) . ': ' . $this->inlineMixedToTs($value);
        }

        return '{ ' . implode(', ', $parts) . ' }';
    }

    /**
     * Group a union before appending a list marker.
     *
     * @param string $type Type string
     * @return string Grouped type string
     */
    protected function group(string $type): string
    {
        return str_contains($type, '|') ? "({$type})" : $type;
    }
}
