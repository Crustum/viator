<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Converter;

use Cake\Core\Container;
use Crustum\Investigator\Analyzer\Analyzer;
use Crustum\Investigator\InvestigatorPlugin;
use Crustum\Investigator\Type\ArrayType;
use Crustum\Investigator\Type\Type;
use Crustum\Scrutator\Collector\InertiaSharedData as SharedDataCollector;
use Crustum\Scrutator\Component\InertiaSharedData as SharedDataComponent;
use Crustum\Viator\Converter\InertiaSharedData;
use PHPUnit\Framework\TestCase;

/**
 * Inertia shared data converter test.
 *
 * Shared props arrive as hand-built Scrutator components (the P4 engine
 * walk collects middleware `share()` methods); the exact analyzed shape
 * renders as `SharedData`, an empty shape keeps the loose shell.
 */
final class InertiaSharedDataTest extends TestCase
{
    /**
     * Exact shapes render prop by prop.
     *
     * @return void
     */
    public function testSharedData(): void
    {
        $found = (new InertiaSharedData())->sharedData(collection([
            new SharedDataComponent(new ArrayType(['user' => Type::string(), 'count' => Type::int()])),
        ]));

        $this->assertArrayHasKey('Inertia.SharedData', $found);
        $this->assertStringContainsString('user: string;', $found['Inertia.SharedData']);
        $this->assertStringContainsString('count: number;', $found['Inertia.SharedData']);
    }

    /**
     * Middleware `share()` shapes render through the TestApp chain.
     *
     * @return void
     */
    public function testSharedThroughMiddleware(): void
    {
        $collector = new SharedDataCollector($this->analyzer());
        $collector->setAppPaths(APP . 'Middleware');

        $component = $collector->collect()
            ->filter(static fn(SharedDataComponent $shared): bool => $shared->data->value !== [])
            ->first();
        $this->assertInstanceOf(SharedDataComponent::class, $component);

        $found = (new InertiaSharedData())->sharedData(collection([$component]));
        $this->assertStringContainsString('locale: string;', $found['Inertia.SharedData']);
        $this->assertStringContainsString('version: number;', $found['Inertia.SharedData']);
    }

    /**
     * Empty shapes keep the loose shell; empty dirs share nothing.
     *
     * @return void
     */
    public function testEmptyKeepsLooseShell(): void
    {
        $found = (new InertiaSharedData())->sharedData(collection([
            new SharedDataComponent(new ArrayType([])),
        ]));

        $this->assertStringContainsString('[key: string]: unknown;', $found['Inertia.SharedData']);

        $collector = new SharedDataCollector($this->analyzer());
        $collector->setAppPaths(APP . 'Enum');

        $this->assertSame([], $collector->collect()->toList());
    }

    /**
     * Read the analyzer for Scrutator collectors.
     *
     * @return \Crustum\Investigator\Analyzer\Analyzer
     */
    protected function analyzer(): Analyzer
    {
        $container = new Container();
        (new InvestigatorPlugin())->services($container);

        return $container->get(Analyzer::class);
    }
}
