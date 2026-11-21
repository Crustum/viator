<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Converter;

use Crustum\Scrutator\Collector\Enum as EnumCollector;
use Crustum\Scrutator\Support\Inventory;
use Crustum\Viator\Converter\Enums;
use PHPUnit\Framework\TestCase;

/**
 * Enums converter test.
 *
 * Enums come from the Scrutator collector pointed at TestApp: backed enums
 * union their values, pure enums their indices, empty enums `never`.
 */
final class EnumsTest extends TestCase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        Inventory::flush();
    }

    /**
     * Backed, pure, and empty enums map to unions.
     *
     * @return void
     */
    public function testEnums(): void
    {
        $collector = new EnumCollector();
        $collector->setAppPaths(APP . 'Enum');

        $enums = (new Enums())->unions($collector->collect());

        $this->assertSame('"draft" | "published" | "archived"', $enums['TestApp.Enum.PostStatus']);
        $this->assertSame('1 | 2', $enums['TestApp.Enum.Priority']);
        $this->assertSame('0 | 1 | 2', $enums['TestApp.Enum.UnitEnum']);
        $this->assertSame('never', $enums['TestApp.Enum.EmptyEnum']);
    }

    /**
     * No components yield no unions.
     *
     * @return void
     */
    public function testEmptyYieldsNothing(): void
    {
        $this->assertSame([], (new Enums())->unions(collection([])));
    }
}
