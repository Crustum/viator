<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Registry;

use Crustum\Investigator\Type\ClassType;
use Crustum\Investigator\Type\Type;
use Crustum\Investigator\Type\VoidType;
use Crustum\Viator\Registry\TypeScriptConverter;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * TypeScriptConverter test.
 */
final class TypeScriptConverterTest extends TestCase
{
    /**
     * @var \Crustum\Viator\Registry\TypeScriptConverter
     */
    protected TypeScriptConverter $converter;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->converter = new TypeScriptConverter();
    }

    /**
     * Array shape with unknown key produces a record type.
     *
     * @return void
     */
    public function testArrayShapeWithUnknownKeyProducesRecordType(): void
    {
        $arrayShape = Type::arrayShape(Type::mixed(), Type::mixed());

        $this->assertSame('Record<string, unknown>', $this->converter->convert($arrayShape));
    }

    /**
     * Array shape with number key produces an array type.
     *
     * @return void
     */
    public function testArrayShapeWithNumberKeyProducesArrayType(): void
    {
        $arrayShape = Type::arrayShape(Type::int(), Type::string());

        $this->assertSame('string[]', $this->converter->convert($arrayShape));
    }

    /**
     * Array shape with string key produces a record type.
     *
     * @return void
     */
    public function testArrayShapeWithStringKeyProducesRecordType(): void
    {
        $arrayShape = Type::arrayShape(Type::string(), Type::int());

        $this->assertSame('Record<string, number>', $this->converter->convert($arrayShape));
    }

    /**
     * Empty array type produces an unknown list.
     *
     * @return void
     */
    public function testArrayTypeProducesArrayType(): void
    {
        $this->assertSame('unknown[]', $this->converter->convert(Type::array([])));
    }

    /**
     * Entity classes mirror their namespace with dots.
     *
     * @return void
     */
    public function testClassTypeMirrorsNamespace(): void
    {
        $this->assertSame(
            'App.Model.Entity.User',
            $this->converter->convert(new ClassType('App\Model\Entity\User')),
        );
    }

    /**
     * Dates convert to string.
     *
     * @return void
     */
    public function testDateTimeConvertsToString(): void
    {
        $this->assertSame('string', $this->converter->convert(new ClassType(DateTime::class)));
    }

    /**
     * Nullable types carry the null suffix.
     *
     * @return void
     */
    public function testNullableSuffix(): void
    {
        $this->assertSame('string | null', $this->converter->convert(Type::string()->nullable()));
    }

    /**
     * A lone unknown beside real types drops out.
     *
     * @return void
     */
    public function testUnionDropsLoneUnknown(): void
    {
        $this->assertSame(
            'string',
            $this->converter->convert(Type::union(Type::string(), Type::mixed())),
        );
    }

    /**
     * Callables convert to their return type.
     *
     * @return void
     */
    public function testCallableConvertsToReturnType(): void
    {
        $this->assertSame(
            'string',
            $this->converter->convert(Type::callable([], Type::string())),
        );
    }

    /**
     * Dictionaries render as multiline object literals.
     *
     * @return void
     */
    public function testDictionaryRendersObjectLiteral(): void
    {
        $result = $this->converter->convert(Type::array(['name' => Type::string()]));

        $this->assertSame('{' . PHP_EOL . '    name: string,' . PHP_EOL . '}', $result);
    }

    /**
     * Unsupported types throw.
     *
     * @return void
     */
    public function testUnsupportedTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->converter->convert(new VoidType());
    }
}
