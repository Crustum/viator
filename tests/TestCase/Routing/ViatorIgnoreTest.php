<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Routing;

use Cake\Core\Configure;
use Cake\Core\Container;
use Crustum\Investigator\Analyzer\Analyzer;
use Crustum\Investigator\Reflector\Reflector;
use Crustum\Investigator\Support\Markers;
use Crustum\Scrutator\Collector\ControllerAction as ControllerActionCollector;
use Crustum\Scrutator\Component\ControllerAction as ControllerActionComponent;
use Crustum\Scrutator\ScrutatorPlugin;
use Crustum\Scrutator\Support\Ignores;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TestApp\Ignore\IgnoreMethodController;

/**
 * ViatorIgnore marker test.
 *
 * Markers are honored by the Scrutator collector through the Investigator
 * ignore contract, so generation-time filtering agrees with analysis.
 */
final class ViatorIgnoreTest extends TestCase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        Markers::reset();
        Markers::registerConditionResolver(Ignores::evaluate(...));
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        Configure::delete('Viator.testHideConditional');
        Configure::delete('Viator.testKeepConditional');
        Markers::reset();
        parent::tearDown();
    }

    /**
     * Collect actions from the ignore fixtures directory.
     *
     * @return array<int, \Crustum\Scrutator\Component\ControllerAction>
     */
    protected function collect(): array
    {
        $container = new Container();
        (new ScrutatorPlugin())->services($container);
        $collector = new ControllerActionCollector($container->get(Analyzer::class), $container->get(Reflector::class));
        $collector->setAppPaths(
            dirname((string)(new ReflectionClass(IgnoreMethodController::class))->getFileName()),
        );

        return $collector->collect()->toList();
    }

    /**
     * Cake-style handler reference for an action.
     *
     * @param \Crustum\Scrutator\Component\ControllerAction $action Collected action
     * @return string e.g. `IgnoreMethod:index`
     */
    protected function reference(ControllerActionComponent $action): string
    {
        $reference = '';
        if ($action->prefix !== null && $action->prefix !== '') {
            $reference .= $action->prefix . ':';
        }

        if ($action->plugin !== null && $action->plugin !== '') {
            $reference .= $action->plugin . '.';
        }

        return $reference . $action->controller . ':' . $action->action;
    }

    /**
     * Unconditional markers drop the action and the class.
     *
     * @return void
     */
    public function testScanSkipsIgnoredDeclarations(): void
    {
        $names = array_map($this->reference(...), $this->collect());

        $this->assertContains('IgnoreMethod:index', $names);
        $this->assertNotContains('IgnoreMethod:hidden', $names);
        $this->assertNotContains('IgnoreWhole:index', $names);
    }

    /**
     * Conditional markers follow Configure flags.
     *
     * @return void
     */
    public function testConditionalMarkersFollowConfigure(): void
    {
        $names = array_map($this->reference(...), $this->collect());
        $this->assertContains('IgnoreMethod:conditional', $names);
        $this->assertNotContains('IgnoreMethod:keptUnless', $names);

        Markers::reset();
        Markers::registerConditionResolver(Ignores::evaluate(...));
        Configure::write('Viator.testHideConditional', true);
        $names = array_map($this->reference(...), $this->collect());
        $this->assertNotContains('IgnoreMethod:conditional', $names);

        Markers::reset();
        Markers::registerConditionResolver(Ignores::evaluate(...));
        Configure::delete('Viator.testHideConditional');
        Configure::write('Viator.testKeepConditional', true);
        $names = array_map($this->reference(...), $this->collect());
        $this->assertContains('IgnoreMethod:keptUnless', $names);
    }
}
