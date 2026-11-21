<?php
declare(strict_types=1);

namespace Crustum\Viator\Registry;

use Crustum\Investigator\Type\Contract\Type;

/**
 * Base converter behind the registry.
 */
abstract class AbstractConverter implements ConverterInterface
{
    /**
     * Convert an analyzed type.
     *
     * @param \Crustum\Investigator\Type\Contract\Type $result Analyzed type
     * @return string TypeScript type
     */
    abstract public function convert(Type $result): string;
}
