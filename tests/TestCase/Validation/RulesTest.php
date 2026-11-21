<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Validation;

use Crustum\Viator\Validation\Rules;
use PHPUnit\Framework\TestCase;
use TestApp\Enum\QuotedEnum;

/**
 * Validation rules test: presence, type mapping, literal unions.
 */
final class RulesTest extends TestCase
{
    /**
     * @return void
     */
    public function testInListResolvesLiteralUnion(): void
    {
        $rules = new Rules(true, ['inList'], ['a', 'b']);

        $this->assertTrue($rules->isRequired());
        $this->assertSame('"a" | "b"', $rules->resolveFieldType());
    }

    /**
     * @return void
     */
    public function testEnumRuleReferencesSharedType(): void
    {
        $rules = new Rules(true, [], null, QuotedEnum::class);

        $this->assertSame('TestApp.Enum.QuotedEnum', $rules->resolveFieldType());
    }

    /**
     * @return void
     */
    public function testInListWinsOverEnumReference(): void
    {
        $rules = new Rules(true, [], ['a'], QuotedEnum::class);

        $this->assertSame('"a"', $rules->resolveFieldType());
    }

    /**
     * @return void
     */
    public function testFileRulesResolveToFile(): void
    {
        foreach (['uploadedFile', 'allowEmptyFile', 'image'] as $kind) {
            $this->assertSame('File', (new Rules(false, [$kind]))->resolveFieldType(), $kind);
        }
    }

    /**
     * @return void
     */
    public function testNumericAndBooleanKinds(): void
    {
        $this->assertSame('number', (new Rules(true, ['integer']))->resolveFieldType());
        $this->assertSame('number', (new Rules(true, ['numeric']))->resolveFieldType());
        $this->assertSame('boolean', (new Rules(true, ['boolean']))->resolveFieldType());
        $this->assertSame('unknown[]', (new Rules(false, ['allowEmptyArray']))->resolveFieldType());
    }

    /**
     * @return void
     */
    public function testDefaultsToString(): void
    {
        $this->assertSame('string', (new Rules(true, ['maxLength', 'email']))->resolveFieldType());
        $this->assertSame('string', (new Rules(false, []))->resolveFieldType());
    }
}
