<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Lang;

use Crustum\Viator\Lang\Trait\CanExportTrait;
use Crustum\Viator\Lang\Trait\HasMetaTrait;
use PHPUnit\Framework\TestCase;

/**
 * Langs concerns test.
 */
final class ConcernsTest extends TestCase
{
    /**
     * Build a test double using both traits.
     *
     * @return object Anonymous declaration builder
     */
    protected function declaration(): object
    {
        return new class {
            use CanExportTrait;
            use HasMetaTrait;

            /**
             * @return string
             */
            public function render(): string
            {
                return $this->exportFormatted() . 'const x = 1;';
            }

            /**
             * @return string
             */
            public function docs(): string
            {
                return $this->meta();
            }
        };
    }

    /**
     * @return void
     */
    public function testExportFlags(): void
    {
        $this->assertSame('const x = 1;', $this->declaration()->render());
        $this->assertSame('export const x = 1;', $this->declaration()->export()->render());
        $this->assertSame('export default const x = 1;', $this->declaration()->exportDefault()->render());
    }

    /**
     * @return void
     */
    public function testMetaReferences(): void
    {
        $docs = $this->declaration()
            ->referenceMethod('App\\Controller\\Pages', 'display')
            ->referenceClass('App\\Model\\Entity\\Contact', 'src/Model/Entity/Contact.php')
            ->docs();

        $this->assertStringContainsString('@see \\App\\Controller\\Pages::display', $docs);
        $this->assertStringContainsString('@see [\\App\\Model\\Entity\\Contact]', $docs);
        $this->assertSame('', $this->declaration()->docs());
    }
}
