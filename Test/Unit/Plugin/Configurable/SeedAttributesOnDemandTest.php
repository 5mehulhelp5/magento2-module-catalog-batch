<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Plugin\Configurable;

use Kingletas\CatalogBatch\Model\AttributeCollectionSeeder;
use Kingletas\CatalogBatch\Model\PendingConfigurables;
use Kingletas\CatalogBatch\Plugin\Configurable\SeedAttributesOnDemand;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use PHPUnit\Framework\TestCase;

class SeedAttributesOnDemandTest extends TestCase
{
    private const string KEY = '_cache_instance_configurable_attributes';

    /** @var array<int, int[]> The entity ids of each seeding. */
    private array $seedings = [];

    private PendingConfigurables $pending;

    protected function setUp(): void
    {
        $this->pending = new PendingConfigurables();
    }

    public function testTheFirstAskAnswersEveryProductTheCollectionLoaded(): void
    {
        [$five, $six] = [$this->product(5), $this->product(6)];
        $this->pending->remember([$five, $six], 1, 1);

        $this->plugin()->beforeGetConfigurableAttributes($this->createMock(Configurable::class), $six);
        $this->plugin()->beforeGetConfigurableAttributes($this->createMock(Configurable::class), $five);

        $this->assertSame([[5, 6]], $this->seedings);
        $this->assertTrue($five->hasData(self::KEY));
    }

    /**
     * Magento can ask with a different object for the same product, and that object should get the same answer.
     */
    public function testAnotherObjectForARememberedProductGetsItsTwinsAnswer(): void
    {
        $this->pending->remember([$this->product(5)], 1, 1);
        $asked = $this->product(5);

        $this->plugin()->beforeGetConfigurableAttributes($this->createMock(Configurable::class), $asked);

        $this->assertSame('seeded-5', $asked->getData(self::KEY));
    }

    public function testAProductNothingRememberedIsLeftToMagento(): void
    {
        $asked = $this->product(9);

        $subject = $this->createMock(Configurable::class);

        $this->assertNull($this->plugin()->beforeGetConfigurableAttributes($subject, $asked));
        $this->assertSame([], $this->seedings);
        $this->assertFalse($asked->hasData(self::KEY));
    }

    private function plugin(): SeedAttributesOnDemand
    {
        $seeder = $this->createMock(AttributeCollectionSeeder::class);
        $seeder->method('seed')->willReturnCallback(function (array $products): int {
            $this->seedings[] = array_map(static fn (Product $product): int => (int) $product->getId(), $products);

            foreach ($products as $product) {
                $product->setData(self::KEY, 'seeded-' . $product->getId());
            }

            return count($products);
        });

        return new SeedAttributesOnDemand($this->pending, $seeder);
    }

    private function product(int $id): Product
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getTypeId'])
            ->getMock();
        $product->method('getId')->willReturn($id);
        $product->method('getTypeId')->willReturn('configurable');

        return $product;
    }
}
