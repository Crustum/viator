<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Attribute;

use Crustum\Investigator\Contract\ConditionallyIgnored;
use Crustum\Viator\Attribute\ViatorIgnore;
use PHPUnit\Framework\TestCase;

/**
 * ViatorIgnore marker contract test.
 */
final class ViatorIgnoreTest extends TestCase
{
    /**
     * The marker satisfies the Investigator ignore contract.
     *
     * @return void
     */
    public function testImplementsConditionallyIgnored(): void
    {
        $this->assertInstanceOf(ConditionallyIgnored::class, new ViatorIgnore());
    }

    /**
     * Accessors expose the configured conditions.
     *
     * @return void
     */
    public function testExposesUnlessAndWhen(): void
    {
        $marker = new ViatorIgnore(unless: 'Viator.keep', when: [self::class, 'check']);

        $this->assertSame('Viator.keep', $marker->unless());
        $this->assertSame([self::class, 'check'], $marker->when());
        $this->assertNull((new ViatorIgnore())->unless());
        $this->assertNull((new ViatorIgnore())->when());
    }
}
