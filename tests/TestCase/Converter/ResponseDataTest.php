<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Converter;

use Crustum\Investigator\Type\Type;
use Crustum\Scrutator\Component\JsonApiResponse;
use Crustum\Scrutator\Component\ResourceResponse;
use Crustum\Viator\Converter\JsonApiData;
use Crustum\Viator\Converter\ResourceData;
use PHPUnit\Framework\TestCase;

/**
 * Resource and JSON:API converters test.
 *
 * Responses arrive as hand-built Scrutator components (the P4 engine walk
 * feeds route actions through the Response collector); shapes render
 * through the P2 type converter with upstream wrap/collection semantics.
 * Cake actions never produce plain JSON responses (return values are
 * ignored at runtime), so there is no JSON converter.
 */
final class ResponseDataTest extends TestCase
{
    /**
     * Resources group unions for collections and nest under `data`.
     *
     * @return void
     */
    public function testResource(): void
    {
        $single = (new ResourceData())->convert(new ResourceResponse(
            'App\\Resource\\UserResource',
            Type::array(['id' => Type::int()]),
            false,
            null,
        ));
        $this->assertSame('{ id: number }', $single);

        $collection = (new ResourceData())->convert(new ResourceResponse(
            'App\\Resource\\UserResource',
            Type::union(Type::string(), Type::int()),
            true,
            null,
        ));
        $this->assertSame('(string | number)[]', $collection);

        $wrapped = (new ResourceData())->convert(new ResourceResponse(
            'App\\Resource\\UserResource',
            ['id' => Type::int()],
            false,
            null,
            ['meta' => Type::string()],
        ));
        $this->assertSame('{ data: { id: number }, meta: string }', $wrapped);
    }

    /**
     * JSON:API renders `data` with optional sections.
     *
     * @return void
     */
    public function testJsonApi(): void
    {
        $type = (new JsonApiData())->convert(new JsonApiResponse(
            'App\\Resource\\UserResource',
            ['name' => Type::string()],
            [],
            ['self' => Type::string()],
            [],
            false,
        ));

        $this->assertSame(
            '{ data: { id: string, type: string, attributes?: { name: string }, links?: { self: string } } }',
            $type,
        );

        $collection = (new JsonApiData())->convert(new JsonApiResponse(
            'App\\Resource\\UserResource',
            [],
            [],
            [],
            [],
            true,
        ));
        $this->assertSame(
            '{ data: { id: string, type: string }[] }',
            $collection,
        );
    }
}
