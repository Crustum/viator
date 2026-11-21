<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Lang;

use Crustum\Viator\Lang\TypeScript;
use PHPUnit\Framework\TestCase;

/**
 * TypeScript emission helpers test, including quoting edge cases.
 */
final class TypeScriptTest extends TestCase
{
    /**
     * @return void
     */
    public function testQuoteEscapesDoubleQuotes(): void
    {
        $this->assertSame('"say \"hi\""', TypeScript::quote('say "hi"'));
    }

    /**
     * @return void
     */
    public function testQuoteLeavesSingleQuotesAlone(): void
    {
        $this->assertSame('"it\'s here"', TypeScript::quote("it's here"));
    }

    /**
     * @return void
     */
    public function testQuoteEscapesBackslashes(): void
    {
        $this->assertSame('"back\\\\slash"', TypeScript::quote('back\slash'));
    }

    /**
     * @return void
     */
    public function testQuoteEscapesNewlinesAndControlCharacters(): void
    {
        $this->assertSame('"line\nbreak"', TypeScript::quote("line\nbreak"));
        $this->assertSame('"tab\there"', TypeScript::quote("tab\there"));
    }

    /**
     * @return void
     */
    public function testQuoteEscapesLineSeparators(): void
    {
        $this->assertSame('"a\\u2028b"', TypeScript::quote("a\u{2028}b"));
        $this->assertSame('"a\\u2029b"', TypeScript::quote("a\u{2029}b"));
    }

    /**
     * @return void
     */
    public function testQuoteLeavesSlashesAndUnicodeUnescaped(): void
    {
        $this->assertSame('"Pages/Users/Edit"', TypeScript::quote('Pages/Users/Edit'));
        $this->assertSame('"café"', TypeScript::quote('café'));
    }

    /**
     * @return void
     */
    public function testQuoteKeyQuotesOnlyNonIdentifiers(): void
    {
        $this->assertSame('foo', TypeScript::quoteKey('foo'));
        $this->assertSame('"for-sale"', TypeScript::quoteKey('for-sale'));
        $this->assertSame('"say \"hi\""', TypeScript::quoteKey('say "hi"'));
    }

    /**
     * @return void
     */
    public function testUnionDefaultsToUnknown(): void
    {
        $this->assertSame('unknown', TypeScript::union([]));
        $this->assertSame('string | number', TypeScript::union(['string', 'number', 'string']));
    }

    /**
     * @return void
     */
    public function testIndentPrefixesEveryLine(): void
    {
        $eol = PHP_EOL;
        $this->assertSame("    a{$eol}    b", TypeScript::indent("a{$eol}b"));
        $this->assertSame('        a', TypeScript::indent('a', 2));
    }

    /**
     * @return void
     */
    public function testSafeMethodAppendsSuffixToReservedWords(): void
    {
        $this->assertSame('deleteMethod', TypeScript::safeMethod('delete', 'Method'));
    }

    /**
     * @return void
     */
    public function testSafeMethodCamelsDashedNames(): void
    {
        $this->assertSame('myMethod', TypeScript::safeMethod('my-method', 'Method'));
    }

    /**
     * @return void
     */
    public function testSafeMethodPrefixesInvalidStart(): void
    {
        $this->assertSame('method2fa', TypeScript::safeMethod('2fa', 'Method'));
    }

    /**
     * @return void
     */
    public function testSafeMethodKeepsPlainNames(): void
    {
        $this->assertSame('view', TypeScript::safeMethod('view', 'Method'));
    }

    /**
     * @return void
     */
    public function testQuoteIfNeeded(): void
    {
        $this->assertSame('123', TypeScript::quoteIfNeeded('123'));
        $this->assertSame('"9lives"', TypeScript::quoteIfNeeded('9lives'));
        $this->assertSame('name', TypeScript::quoteIfNeeded('name'));
    }

    /**
     * @return void
     */
    public function testDockblock(): void
    {
        $eol = PHP_EOL;
        $this->assertSame(
            "/**{$eol} * @see \\App\\Controller\\Pages::display{$eol} * @route /pages{$eol} */",
            TypeScript::dockblock(['@see \\App\\Controller\\Pages::display', '@route /pages']),
        );
    }
}
