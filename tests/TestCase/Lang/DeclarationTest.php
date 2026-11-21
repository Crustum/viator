<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Lang;

use Crustum\Viator\Lang\TypeScript\Declaration;
use PHPUnit\Framework\TestCase;

/**
 * Declaration builder test.
 */
final class DeclarationTest extends TestCase
{
    /**
     * @return void
     */
    public function testRendersExportedType(): void
    {
        $rendered = Declaration::type('Status', '"active" | "archived"')->export()->render();

        $this->assertSame('export type Status = "active" | "archived";', $rendered);
    }

    /**
     * @return void
     */
    public function testRendersUnexportedConst(): void
    {
        $rendered = Declaration::constant('x', '`y`')->render();

        $this->assertSame('const x = `y`;', $rendered);
    }

    /**
     * @return void
     */
    public function testRendersMetadata(): void
    {
        $eol = PHP_EOL;
        $rendered = Declaration::type('Contact', '{ id: number; }')
            ->export()
            ->referenceClass('App\\Model\\Entity\\Contact')
            ->render();

        $this->assertStringStartsWith("/**{$eol} * @see \\App\\Model\\Entity\\Contact{$eol} */{$eol}", $rendered);
        $this->assertStringEndsWith('export type Contact = { id: number; };', $rendered);
    }
}
