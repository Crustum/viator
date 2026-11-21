<?php
declare(strict_types=1);

namespace Crustum\Viator\Attribute;

use Attribute;
use Crustum\Investigator\Contract\ConditionallyIgnored;

/**
 * Leave the marked declaration out of the generated TypeScript.
 *
 * The controller scanner honors this directly; the marker also satisfies
 * the Investigator ignore contract so analysis-time checks agree with
 * generation-time ones.
 *
 * On a class, nothing is generated for it at all. On a controller action,
 * the action is dropped from `actions/` output (named `routes/` entries are
 * explicit and therefore kept). On an entity property, the field is dropped
 * from its `Model.Entity` type.
 *
 * Pass `unless` to keep it only while a condition holds, or `when` to leave
 * it out only while one holds. A string condition is answered by
 * `Configure::read()`; a `[class, method]` callable is invoked.
 */
#[Attribute(Attribute::TARGET_ALL)]
final class ViatorIgnore implements ConditionallyIgnored
{
    /**
     * @param array{0: class-string, 1: string}|string|null $unless Keep it while this passes
     * @param array{0: class-string, 1: string}|string|null $when Leave it out while this passes
     */
    public function __construct(
        public readonly string|array|null $unless = null,
        public readonly string|array|null $when = null,
    ) {
    }

    /**
     * Keep the declaration while this passes.
     *
     * @return array{0: class-string, 1: string}|string|null Config key, [class, method] callable, or null
     */
    public function unless(): string|array|null
    {
        return $this->unless;
    }

    /**
     * Leave the declaration out while this passes.
     *
     * @return array{0: class-string, 1: string}|string|null Config key, [class, method] callable, or null
     */
    public function when(): string|array|null
    {
        return $this->when;
    }
}
