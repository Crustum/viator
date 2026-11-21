<?php
declare(strict_types=1);

namespace Crustum\Viator\Assembler;

use Cake\Collection\CollectionInterface;
use Crustum\Viator\Converter\EnvironmentVariables;
use Crustum\Viator\Result\Result;

/**
 * `vite-env.d.ts` assembly.
 */
final class EnvAssembler
{
    /**
     * Assemble `vite-env.d.ts` at the output base, if applicable.
     *
     * Explicit dotenv files and JavaScript roots reach the walk through
     * the command (which configures the Scrutator collector before
     * walking), so the converter only filters walked components here.
     *
     * @param string $base Output base path
     * @param \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\EnvironmentVariable>|null $variables Scrutator components from the walk, null when skipped
     * @return \Crustum\Viator\Result\Result|null Null when no env file applies
     */
    public function assemble(string $base, ?CollectionInterface $variables = null): ?Result
    {
        return (new EnvironmentVariables($base))->convert($variables);
    }
}
