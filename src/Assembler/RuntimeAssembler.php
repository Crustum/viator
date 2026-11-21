<?php
declare(strict_types=1);

namespace Crustum\Viator\Assembler;

use Cake\Core\Configure;
use Crustum\Viator\Result\Result;

/**
 * Framework-agnostic runtime assembly.
 *
 * Bakes `Viator.urlDefaults` into the runtime as a `setUrlDefaults()`
 * call so generated functions apply them without app bootstrap code.
 */
final class RuntimeAssembler
{
    /**
     * @param string $runtimeDir Output subdirectory for the runtime
     * @return \Crustum\Viator\Result\Result
     */
    public function assemble(string $runtimeDir): Result
    {
        $source = dirname(__DIR__, 2) . DS . 'resources' . DS . 'js' . DS . 'viator.ts';

        $runtime = str_replace(["\r\n", "\r"], "\n", (string)file_get_contents($source));

        $defaults = Configure::read('Viator.urlDefaults', []);
        if (is_array($defaults) && $defaults !== []) {
            $runtime .= "\nsetUrlDefaults(" . json_encode($defaults) . ");\n";
        }

        return new Result($runtimeDir . '/index.ts', $runtime);
    }
}
