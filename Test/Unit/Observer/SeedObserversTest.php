<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Observer;

use Kingletas\CatalogBatch\Model\Config;
use Kingletas\CatalogBatch\Model\PendingConfigurables;
use Kingletas\CatalogBatch\Model\Salability\SalableChildCounter;
use Kingletas\CatalogBatch\Observer\SeedListingConfigurables;
use Kingletas\CatalogBatch\Observer\SeedViewedConfigurable;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class SeedObserversTest extends TestCase
{
    private PendingConfigurables $pending;

    /** @var list<array{0: int[], 1: int}> Product ids and store id each listing was noted with. */
    private array $counted = [];

    protected function setUp(): void
    {
        $this->pending = new PendingConfigurables();
    }

    public function testAListingIsNotedForTheSalableCountWithItsStore(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([1 => $this->configurable(7), 2 => $this->configurable(8)]);

        $this->listingObserver()->execute($this->observer(['collection' => $collection]));

        $this->assertSame([[[7, 8], 3]], $this->counted);
    }

    /**
     * The salable count has its own switch, so a listing is noted for it even when attribute batching is off.
     */
    public function testTheSalableCountDoesNotDependOnTheAttributeSwitch(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([1 => $this->configurable(7)]);

        $this->listingObserver(false)->execute($this->observer(['collection' => $collection]));

        $this->assertNull($this->pending->takeGroupOf(7));
        $this->assertSame([[[7], 3]], $this->counted);
    }

    /**
     * Nothing is queried when a collection loads; the configurables wait until the page asks about one.
     */
    public function testAListingIsRememberedWithItsOwnStoreAndWebsite(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([1 => $this->configurable(7)]);

        $this->listingObserver()->execute($this->observer(['collection' => $collection]));

        $group = $this->pending->takeGroupOf(7);
        $this->assertNotNull($group);
        $this->assertSame([7], array_keys($group->products));
        $this->assertSame([3, 4], [$group->storeId, $group->websiteId]);
    }

    public function testTheSwitchBeingOffMeansNothingIsAsked(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([1 => $this->configurable(7)]);

        $this->listingObserver(false)->execute($this->observer(['collection' => $collection]));

        $this->assertNull($this->pending->takeGroupOf(7));
    }

    /**
     * Another module can dispatch this event with anything, so the observer has to check what it was handed.
     */
    public function testSomethingOtherThanAProductCollectionIsIgnored(): void
    {
        $this->listingObserver()->execute($this->observer(['collection' => new DataObject()]));

        $this->assertNull($this->pending->takeGroupOf(7));
        $this->assertSame([], $this->counted);
    }

    public function testAProductPageRemembersTheOneProductItIsShowing(): void
    {
        $this->viewObserver()->execute($this->observer(['product' => $this->configurable(7)]));

        $this->assertNotNull($this->pending->takeGroupOf(7));
    }

    public function testAProductViewWithNoProductIsIgnored(): void
    {
        $this->viewObserver()->execute($this->observer([]));

        $this->assertNull($this->pending->takeGroupOf(7));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function observer(array $data): Observer
    {
        return new Observer(['event' => new Event($data)]);
    }

    private function listingObserver(bool $enabled = true): SeedListingConfigurables
    {
        $counter = $this->createMock(SalableChildCounter::class);
        $counter->method('remember')->willReturnCallback(function (array $products, int $storeId): void {
            $this->counted[] = [
                array_map(static fn (Product $product): int => (int) $product->getId(), $products),
                $storeId,
            ];
        });

        return new SeedListingConfigurables($this->config($enabled), $this->pending, $this->stores(), $counter);
    }

    private function viewObserver(bool $enabled = true): SeedViewedConfigurable
    {
        return new SeedViewedConfigurable($this->config($enabled), $this->pending, $this->stores());
    }

    private function config(bool $enabled): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn($enabled);

        return $config;
    }

    private function configurable(int $id): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getTypeId')->willReturn('configurable');
        $product->method('hasData')->willReturn(false);

        return $product;
    }

    private function stores(): StoreManagerInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(3);
        $store->method('getWebsiteId')->willReturn(4);

        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return $stores;
    }
}
