<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Validation;

use Crustum\Viator\Validation\Rules;
use PHPUnit\Framework\TestCase;

/**
 * Rules upstream-fidelity test.
 */
final class RulesFidelityTest extends TestCase
{
    /**
     * Digits rules do not exist in CakePHP validators.
     *
     * Upstream maps Laravel `digits`/`digits_between` to `number`, but the
     * Cake `Validator` has no such methods, so those kinds can never arrive
     * here. Only the template-literal `decimal` (a real Cake method) is
     * covered.
     *
     * @return void
     */
    public function testDecimalTemplate(): void
    {
        $this->assertSame('`${number}.${number}`', (new Rules(true, ['decimal']))->resolveFieldType());
    }
}
