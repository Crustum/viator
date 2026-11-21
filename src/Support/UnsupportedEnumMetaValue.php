<?php
declare(strict_types=1);

namespace Crustum\Viator\Support;

use RuntimeException;

/**
 * Thrown when an enum metadata value has no TypeScript literal form.
 */
final class UnsupportedEnumMetaValue extends RuntimeException
{
}
