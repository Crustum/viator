<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Converter;

use Crustum\Investigator\Type\Type;
use Crustum\Scrutator\Component\InertiaResponse;
use Crustum\Viator\Converter\InertiaData;
use PHPUnit\Framework\TestCase;

/**
 * Inertia pages converter test.
 *
 * Pages arrive as hand-built Scrutator components (the P4 engine walk feeds
 * route actions through the Response collector into the component registry);
 * the converter maps them to `Inertia.Pages.*` types intersecting the
 * `SharedData` base and nests them into a `Pages` block.
 */
final class InertiaDataTest extends TestCase
{
    /**
     * Components map to SharedData-intersecting page types.
     *
     * @return void
     */
    public function testPageTypes(): void
    {
        $pages = (new InertiaData())->pageTypes([
            new InertiaResponse('Dashboard', ['user' => Type::string()]),
            new InertiaResponse('Contacts/Index', ['contacts' => Type::array([])]),
            new InertiaResponse('Authorization::Login', ['status' => Type::string()]),
            new InertiaResponse('Empty', []),
        ]);

        $this->assertSame(
            ['Inertia.Pages.Authorization.Login', 'Inertia.Pages.Contacts.Index', 'Inertia.Pages.Dashboard', 'Inertia.Pages.Empty'],
            array_keys($pages),
        );
        $this->assertStringContainsString('SharedData & {', $pages['Inertia.Pages.Dashboard']);
        $this->assertStringContainsString('user: string;', $pages['Inertia.Pages.Dashboard']);
        $this->assertSame('SharedData', $pages['Inertia.Pages.Empty']);
    }

    /**
     * Pages nest into a block; leaves and branches merge.
     *
     * @return void
     */
    public function testRenderPages(): void
    {
        $converter = new InertiaData();
        $pages = $converter->pageTypes([
            new InertiaResponse('Items', ['items' => Type::array([])]),
            new InertiaResponse('Items/Edit', ['item' => Type::array(['id' => Type::int()])]),
            new InertiaResponse('settings/two-factor', ['enabled' => Type::bool()]),
        ]);

        $block = $converter->renderPages($pages);

        $this->assertStringContainsString('export namespace Pages {', $block);
        $this->assertStringContainsString('export type Items = SharedData & {', $block);
        $this->assertStringContainsString('export namespace Items {', $block);
        $this->assertStringContainsString('export type Edit = SharedData & {', $block);
        $this->assertStringContainsString('export namespace Settings {', $block);
        $this->assertStringContainsString('export type TwoFactor = SharedData & {', $block);
    }
}
