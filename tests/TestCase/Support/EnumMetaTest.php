<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Support;

use Crustum\Viator\Support\EnumMeta;
use PHPUnit\Framework\TestCase;

/**
 * EnumMeta literals test.
 */
final class EnumMetaTest extends TestCase
{
    /**
     * Scalars map to JSON literals.
     *
     * @return void
     */
    public function testEncodesScalars(): void
    {
        $literals = EnumMeta::literals([
            'ACTIVE' => ['label' => 'Active', 'level' => 1, 'flag' => true, 'empty' => null],
        ]);

        $this->assertSame(
            ['ACTIVE' => ['label' => '"Active"', 'level' => '1', 'flag' => 'true', 'empty' => 'null']],
            $literals,
        );
    }

    /**
     * Lists and maps nest inline.
     *
     * @return void
     */
    public function testEncodesNestedStructures(): void
    {
        $literals = EnumMeta::literals([
            'CASE' => [
                'list' => ['a', 'b'],
                'map' => ['x' => 1, 'y-key' => 2],
            ],
        ]);

        $this->assertSame(
            ['CASE' => ['list' => '["a", "b"]', 'map' => '{x: 1, "y-key": 2}']],
            $literals,
        );
    }

    /**
     * Unencodable values drop that key only.
     *
     * @return void
     */
    public function testSkipsUnencodableValuesPerKey(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->assertIsResource($resource);

        try {
            $literals = EnumMeta::literals([
                'CASE' => ['good' => 'ok', 'bad' => $resource],
            ]);

            $this->assertSame(['CASE' => ['good' => '"ok"']], $literals);
        } finally {
            fclose($resource);
        }
    }
}
