<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Converter;

use Cake\Core\Container;
use Crustum\Investigator\Analyzer\Analyzer;
use Crustum\Investigator\InvestigatorPlugin;
use Crustum\Scrutator\Collector\Form as FormCollector;
use Crustum\Scrutator\Support\Inventory;
use Crustum\Viator\Converter\Forms;
use PHPUnit\Framework\TestCase;

/**
 * Forms converter test.
 *
 * Forms come from the Scrutator layer pointed at TestApp: the collector
 * reads `rules()` and `validationDefault()` through the Investigator, and
 * the converter merges every form of a namespace path into one block.
 */
final class FormsTest extends TestCase
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
     * Validator DSL maps to required/optional typed fields with nesting,
     * and forms sharing a namespace merge into one block.
     *
     * @return void
     */
    public function testForms(): void
    {
        $collector = new FormCollector($this->analyzer());
        $collector->setAppPaths(APP . 'Form');

        $blocks = (new Forms())->blocks($collector->collect());

        $this->assertArrayHasKey('TestApp.Form', $blocks);
        $this->assertCount(1, $blocks);
        $block = $blocks['TestApp.Form'];
        $this->assertStringContainsString('export namespace TestApp {', $block);
        $this->assertSame(1, substr_count($block, 'export namespace TestApp {'));
        $this->assertStringContainsString('export type StorePostForm = {', $block);
        $this->assertStringContainsString('title: string;', $block);
        $this->assertStringContainsString('status: "say \\"hi\\"" | "it\'s here" | "back\\\\slash" | "line\\nbreak";', $block);
        $this->assertStringContainsString('format: TestApp.Enum.QuotedEnum;', $block);
        $this->assertStringContainsString('body: string;', $block);
        $this->assertStringContainsString('excerpt?: string;', $block);
        $this->assertStringContainsString('author_email?: string;', $block);
        $this->assertStringContainsString('age: number;', $block);
        $this->assertStringContainsString('avatar?: File;', $block);
        $this->assertStringContainsString('cover?: File;', $block);
        $this->assertStringContainsString('meta: {', $block);
        $this->assertStringContainsString('description: string;', $block);
        $this->assertStringContainsString('export type ContactForm = {', $block);
        $this->assertStringContainsString('name: string;', $block);
        $this->assertStringContainsString('message: string;', $block);
    }

    /**
     * Directories without forms yield nothing.
     *
     * @return void
     */
    public function testSkipsNonForms(): void
    {
        $collector = new FormCollector($this->analyzer());
        $collector->setAppPaths(APP . 'Enum');

        $this->assertSame([], (new Forms())->blocks($collector->collect()));
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
