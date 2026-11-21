<?php
declare(strict_types=1);

namespace Crustum\Viator\Result;

/**
 * One generated TypeScript file: path relative to the output base plus content.
 *
 * Files carry their own import lines, written by the command's Twig
 * rendering, so results stay plain name-plus-content values.
 */
final class Result
{
    /**
     * @param string $name File name relative to the output base, e.g. `viator/types.d.ts`
     * @param string $content File content
     */
    public function __construct(
        public readonly string $name,
        public readonly string $content,
    ) {
    }
}
