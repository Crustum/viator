<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Converter;

use Crustum\Scrutator\Collector\Enum as EnumCollector;
use Crustum\Scrutator\Component\Enum as EnumComponent;
use Crustum\Viator\Converter\Enums;
use PHPUnit\Framework\TestCase;
use TestApp\Enum\EmptyEnum;
use TestApp\Enum\OrderStatus;
use TestApp\Enum\PostStatus;
use TestApp\Enum\Priority;
use TestApp\Enum\ProductStatus;
use TestApp\Enum\UnitEnum;

/**
 * Enums Scrutator-consumer test.
 */
final class EnumsScrutatorTest extends TestCase
{
    /**
     * Components convert like scanned sources, pure enums keep indices.
     *
     * @return void
     */
    public function testUnions(): void
    {
        $components = collection([
            new EnumComponent(PostStatus::class, ['Draft' => 'draft', 'Published' => 'published', 'Archived' => 'archived']),
            new EnumComponent(Priority::class, ['Low' => 1, 'High' => 2]),
            new EnumComponent(UnitEnum::class, ['None' => 0, 'Open' => 1, 'Done' => 2]),
            new EnumComponent(EmptyEnum::class, []),
        ]);

        $unions = (new Enums())->unions($components);

        $this->assertSame('"draft" | "published" | "archived"', $unions['TestApp.Enum.PostStatus']);
        $this->assertSame('1 | 2', $unions['TestApp.Enum.Priority']);
        $this->assertSame('0 | 1 | 2', $unions['TestApp.Enum.UnitEnum']);
        $this->assertSame('never', $unions['TestApp.Enum.EmptyEnum']);
    }

    /**
     * One enum renders its file with constants, const object, and default.
     *
     * @return void
     */
    public function testConvert(): void
    {
        $component = (new EnumComponent(
            PostStatus::class,
            ['Draft' => 'draft', 'Published' => 'published', 'Archived' => 'archived'],
        ))->setFilePath(APP . 'Enum' . DS . 'PostStatus.php');

        $result = (new Enums())->convert($component);

        $this->assertSame('TestApp/Enum/PostStatus.ts', $result->name);
        $this->assertStringContainsString('export const Draft = "draft"', $result->content);
        $this->assertStringContainsString('export const Published = "published"', $result->content);
        $this->assertStringContainsString('export const Archived = "archived"', $result->content);
        $this->assertStringContainsString(
            'export const PostStatus = { Draft, Published, Archived } as const',
            $result->content,
        );
        $this->assertStringContainsString('export default PostStatus', $result->content);
        $this->assertStringContainsString('@see [\\TestApp\\Enum\\PostStatus](/tests/TestApp/Enum/PostStatus.php)', $result->content);
        $this->assertStringNotContainsString('Meta', $result->content);
    }

    /**
     * Reserved case names skip individual constants but stay in the object.
     *
     * @return void
     */
    public function testConvertReserved(): void
    {
        $component = new EnumComponent(
            ProductStatus::class,
            ['new' => 'new', 'used' => 'used', 'for' => 'for-sale', 'Active' => 'active'],
        );

        $result = (new Enums())->convert($component);

        $this->assertStringNotContainsString('export const new = ', $result->content);
        $this->assertStringNotContainsString('export const for = ', $result->content);
        $this->assertStringContainsString('export const used = "used"', $result->content);
        $this->assertStringContainsString('export const Active = "active"', $result->content);
        $this->assertStringContainsString(
            'export const ProductStatus = { new: "new", used, for: "for-sale", Active } as const',
            $result->content,
        );
    }

    /**
     * Empty enums render an empty object with a default export.
     *
     * @return void
     */
    public function testConvertEmpty(): void
    {
        $component = new EnumComponent(EmptyEnum::class, []);

        $result = (new Enums())->convert($component);

        $this->assertStringContainsString('export const EmptyEnum = {} as const', $result->content);
        $this->assertStringContainsString('export default EmptyEnum', $result->content);
        $this->assertStringNotContainsString('export const EmptyEnumMeta', $result->content);
    }

    /**
     * Resolved methods render a value-keyed `NameMeta` constant.
     *
     * @return void
     */
    public function testConvertMeta(): void
    {
        $collector = new EnumCollector();
        $collector->setAppPaths(APP . 'Enum');

        $component = $collector->get(OrderStatus::class);
        $this->assertInstanceOf(EnumComponent::class, $component);

        $result = (new Enums())->withMethods()->convert($component);

        $this->assertStringContainsString('export const OrderStatusMeta = {', $result->content);
        $this->assertStringContainsString('    pending: {', $result->content);
        $this->assertStringContainsString('label: "Awaiting Payment",', $result->content);
        $this->assertStringContainsString('color: "gray",', $result->content);
        $this->assertStringContainsString('description: null,', $result->content);
        $this->assertStringContainsString('isFinal: false,', $result->content);
        $this->assertStringContainsString('weight: 1,', $result->content);
        $this->assertStringContainsString('tags: ["order", "pending"],', $result->content);
        $this->assertStringContainsString('channel: "database",', $result->content);
        $this->assertStringContainsString('steps: ["placed", "pending"],', $result->content);
        $this->assertStringContainsString('heading: "AWAITING PAYMENT",', $result->content);
        $this->assertStringContainsString('slug: "order-pending",', $result->content);
        $this->assertStringContainsString('icon: "icon-pending",', $result->content);
        $this->assertStringContainsString('trackingUrl: "https://example.com/track/shipped",', $result->content);
        $this->assertStringNotContainsString('trackingUrl: "https://example.com/track/pending",', $result->content);
        $this->assertStringNotContainsString('unavailable:', $result->content);
        $this->assertStringNotContainsString('payload:', $result->content);
        $this->assertStringNotContainsString('internalCode:', $result->content);
        $this->assertStringNotContainsString('note:', $result->content);
        $this->assertStringNotContainsString('touch:', $result->content);
        $this->assertStringNotContainsString('fallback:', $result->content);
    }

    /**
     * Meta stays out without methods and for method-less enums.
     *
     * @return void
     */
    public function testConvertMetaOptIn(): void
    {
        $collector = new EnumCollector();
        $collector->setAppPaths(APP . 'Enum');

        $component = $collector->get(OrderStatus::class);
        $this->assertInstanceOf(EnumComponent::class, $component);
        $this->assertStringNotContainsString('OrderStatusMeta', (new Enums())->convert($component)->content);

        $priority = $collector->get(Priority::class);
        $this->assertInstanceOf(EnumComponent::class, $priority);
        $priorityResult = (new Enums())->withMethods()->convert($priority);
        $this->assertStringContainsString('export const PriorityMeta = {', $priorityResult->content);
        $this->assertStringContainsString('    "1": {', $priorityResult->content);
        $this->assertStringContainsString('label: "Low",', $priorityResult->content);
        $this->assertStringContainsString('multiplier: 0.5,', $priorityResult->content);
        $this->assertStringNotContainsString('stage:', $priorityResult->content);

        $unit = $collector->get(UnitEnum::class);
        $this->assertInstanceOf(EnumComponent::class, $unit);
        $unitResult = (new Enums())->withMethods()->convert($unit);
        $this->assertStringContainsString('export const UnitEnumMeta = {', $unitResult->content);
        $this->assertStringContainsString('    "0": {', $unitResult->content);
        $this->assertStringContainsString('label: "Not Started",', $unitResult->content);

        $product = $collector->get(ProductStatus::class);
        $this->assertInstanceOf(EnumComponent::class, $product);
        $productResult = (new Enums())->withMethods()->convert($product);
        $this->assertStringContainsString('export const ProductStatusMeta = {', $productResult->content);
        $this->assertStringContainsString('    new: {', $productResult->content);
        $this->assertStringContainsString('label: "Brand New",', $productResult->content);
        $this->assertStringContainsString('default: false,', $productResult->content);
    }
}
