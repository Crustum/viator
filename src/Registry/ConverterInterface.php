<?php
declare(strict_types=1);

namespace Crustum\Viator\Registry;

use Crustum\Investigator\Type\Contract\Type;

/**
 * Converts an analyzed type to a TypeScript string.
 */
interface ConverterInterface
{
    /**
     * Convert an analyzed type.
     *
     * @param \Crustum\Investigator\Type\Contract\Type $result Analyzed type
     * @return string TypeScript type
     */
    public function convert(Type $result): string;
}
