<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Converter;

use Cake\Core\Container;
use Crustum\Investigator\Analyzer\Analyzer;
use Crustum\Investigator\InvestigatorPlugin;
use Crustum\Investigator\Type\Type;
use Crustum\Scrutator\Collector\BroadcastEvent as BroadcastEventCollector;
use Crustum\Scrutator\Component\BroadcastChannel as BroadcastChannelComponent;
use Crustum\Scrutator\Component\BroadcastEvent as BroadcastEventComponent;
use Crustum\Viator\Converter\BroadcastChannels;
use Crustum\Viator\Converter\BroadcastEvents;
use PHPUnit\Framework\TestCase;

/**
 * Broadcast converters test.
 *
 * Events come from the Scrutator collector pointed at TestApp: names resolve
 * `static::class` to the FQCN and literals stay as-is, payload shapes render
 * through the P2 type converter. Channels render from hand-built Scrutator
 * registry components.
 */
final class BroadcastTest extends TestCase
{
    /**
     * Collected events resolve names and payload types through the Scrutator chain.
     *
     * @return void
     */
    public function testEvents(): void
    {
        $collector = new BroadcastEventCollector($this->analyzer());
        $collector->setAppPaths(APP . 'Event');

        $converter = new BroadcastEvents('@laravel/echo-vue');
        $results = $converter->convertComponents($collector->collect());
        $byName = [];
        foreach ($results as $result) {
            $byName[$result->name] = $result->content;
        }

        $echo = $byName['echo-broadcast-events.d.ts'];
        $this->assertStringContainsString('".TestApp\\\\Event\\\\OrderShipped": {', $echo);
        $this->assertStringContainsString('orderId: number;', $echo);
        $this->assertStringContainsString('trackingNumber: string;', $echo);
        $this->assertStringContainsString('carrier: string;', $echo);
        $this->assertStringContainsString('".user.notification": {', $echo);
        $this->assertStringContainsString('userId: number;', $echo);
        $this->assertStringContainsString('title: string;', $echo);
        $this->assertStringContainsString('message: string;', $echo);
    }

    /**
     * Results render the union, const object, and echo d.ts.
     *
     * @return void
     */
    public function testConvert(): void
    {
        $container = new Container();
        (new InvestigatorPlugin())->services($container);
        $collector = new BroadcastEventCollector($container->get(Analyzer::class));
        $collector->setAppPaths(APP . 'Event');

        $converter = new BroadcastEvents('@laravel/echo-vue');
        $results = $converter->convertComponents($collector->collect());
        $byName = [];
        foreach ($results as $result) {
            $byName[$result->name] = $result->content;
        }

        $this->assertArrayHasKey('broadcast-events.ts', $byName);
        $this->assertArrayHasKey('echo-broadcast-events.d.ts', $byName);
        $this->assertStringContainsString('export type BroadcastEvent = ".TestApp\\\\Event\\\\OrderShipped" | ".user.notification";', $byName['broadcast-events.ts']);
        $this->assertStringContainsString('OrderShipped: ".TestApp\\\\Event\\\\OrderShipped",', $byName['broadcast-events.ts']);
        $this->assertStringContainsString('notification: ".user.notification",', $byName['broadcast-events.ts']);
        $this->assertStringContainsString('interface Events {', $byName['echo-broadcast-events.d.ts']);
        $this->assertStringContainsString('".user.notification": {', $byName['echo-broadcast-events.d.ts']);
        $this->assertStringContainsString('userId: number;', $byName['echo-broadcast-events.d.ts']);
    }

    /**
     * Hand-built components convert like collected events.
     *
     * @return void
     */
    public function testConvertComponents(): void
    {
        $components = collection([
            (new BroadcastEventComponent(
                'demo.reset',
                'TestApp\\Event\\DemoReset',
                Type::array(['resetAt' => Type::string()]),
            ))->setFilePath(APP . 'Event' . DS . 'DemoReset.php'),
        ]);
        $results = (new BroadcastEvents('@laravel/echo-vue'))->convertComponents($components);
        $byName = [];
        foreach ($results as $result) {
            $byName[$result->name] = $result->content;
        }

        $this->assertArrayHasKey('broadcast-events.ts', $byName);
        $this->assertStringContainsString('export type BroadcastEvent = ".demo.reset";', $byName['broadcast-events.ts']);
        $this->assertStringContainsString('resetAt: string;', $byName['echo-broadcast-events.d.ts']);
    }

    /**
     * Channels render from registry components; params become functions.
     *
     * Static segments nest, `{param}` segments wrap the nearest static
     * segment on the left in an arrow function instead of nesting.
     *
     * @return void
     */
    public function testChannels(): void
    {
        $channels = collection([
            new BroadcastChannelComponent('live-props', static fn(): bool => true),
            new BroadcastChannelComponent('orders.{order}', static fn(): bool => true),
            new BroadcastChannelComponent('user.{userId}.notifications', static fn(): bool => true),
        ]);

        $result = (new BroadcastChannels())->convert($channels);
        $this->assertNotNull($result);
        $this->assertSame('broadcast-channels.ts', $result->name);
        $this->assertStringContainsString('export type BroadcastChannel = "live-props" | "orders.{order}" | "user.{userId}.notifications";', $result->content);
        $this->assertStringContainsString('"live-props": `live-props`,', $result->content);
        $this->assertStringContainsString('orders: (order: string | number) => `orders.${order}`,', $result->content);
        $this->assertStringContainsString('user: (userId: string | number) => ({', $result->content);
        $this->assertStringContainsString('notifications: `user.${userId}.notifications`,', $result->content);
    }

    /**
     * No events yield no results; no channels yield null.
     *
     * @return void
     */
    public function testEmpty(): void
    {
        $this->assertSame([], (new BroadcastEvents())->convertComponents(collection([])));
        $this->assertNull((new BroadcastChannels())->convert(collection([])));

        $collector = new BroadcastEventCollector($this->analyzer());
        $collector->setAppPaths(APP . 'Event');

        $results = (new BroadcastEvents())->convertComponents($collector->collect());
        $this->assertCount(1, $results);
        $this->assertSame('broadcast-events.ts', $results[0]->name);
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
