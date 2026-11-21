<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Converter;

use Cake\Core\Container;
use Crustum\Investigator\Analyzer\Analyzer;
use Crustum\Investigator\InvestigatorPlugin;
use Crustum\Investigator\Type\Type;
use Crustum\Scrutator\Collector\Entity as EntityCollector;
use Crustum\Scrutator\Component\Entity as EntityComponent;
use Crustum\Scrutator\Support\Inventory;
use Crustum\Viator\Converter\Models;
use PHPUnit\Framework\TestCase;

/**
 * Models converter test.
 *
 * Entities come from the Scrutator collector pointed at TestApp: docblock
 * attributes, entity references, and hidden fields map through the P2 type
 * converter into one merged namespace block per namespace path.
 */
final class ModelsTest extends TestCase
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
     * Entities sharing a namespace merge into one block.
     *
     * @return void
     */
    public function testModels(): void
    {
        $collector = new EntityCollector($this->analyzer());
        $collector->setAppPaths(APP . 'Model');

        $blocks = (new Models())->blocks($collector->collect());

        $this->assertArrayHasKey('TestApp.Model.Entity', $blocks);
        $this->assertCount(1, $blocks);
        $block = $blocks['TestApp.Model.Entity'];
        $this->assertStringContainsString('export namespace TestApp {', $block);
        $this->assertStringContainsString('export namespace Model {', $block);
        $this->assertStringContainsString('export namespace Entity {', $block);
        $this->assertSame(1, substr_count($block, 'export namespace TestApp {'));
        $this->assertStringContainsString('export type User = {', $block);
        $this->assertStringContainsString('export type Product = {', $block);
        $this->assertStringContainsString('export type Category = {', $block);
        $this->assertStringNotContainsString('AuditLog', $block);
        $this->assertStringContainsString('id: number;', $block);
        $this->assertStringContainsString('featuredProduct: TestApp.Model.Entity.Product;', $block);
        $this->assertStringContainsString('relatedProducts: TestApp.Model.Entity.Product[];', $block);
        $this->assertStringContainsString('price: number;', $block);
        $this->assertStringContainsString('in_stock: boolean;', $block);
        $this->assertStringContainsString('sku: string | null;', $block);
    }

    /**
     * Hidden fields stay out; prop-less entities are skipped.
     *
     * @return void
     */
    public function testBlocks(): void
    {
        $entity = new EntityComponent('TestApp\\Model\\Entity\\Hidden');
        $entity->addAttribute('id', Type::int());
        $entity->addAttribute('secret', Type::string());
        $entity->setHidden(['secret']);

        $visible = new EntityComponent('TestApp\\Model\\Entity\\Visible');
        $visible->addAttribute('title', Type::string());

        $empty = new EntityComponent('TestApp\\Model\\Entity\\Empty');

        $blocks = (new Models())->blocks(collection([$entity, $visible, $empty]));

        $this->assertArrayHasKey('TestApp.Model.Entity', $blocks);
        $block = $blocks['TestApp.Model.Entity'];
        $this->assertStringContainsString('export type Hidden = {', $block);
        $this->assertStringContainsString('id: number;', $block);
        $this->assertStringNotContainsString('secret', $block);
        $this->assertStringContainsString('export type Visible = {', $block);
        $this->assertStringNotContainsString('export type Empty', $block);
    }

    /**
     * Directories without entities yield nothing.
     *
     * @return void
     */
    public function testSkipsNonEntities(): void
    {
        $collector = new EntityCollector($this->analyzer());
        $collector->setAppPaths(APP . 'Enum');

        $this->assertSame([], (new Models())->blocks($collector->collect()));
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
