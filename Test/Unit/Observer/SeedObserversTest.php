<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Observer;

use Kingletas\CatalogBatch\Model\AttributeCollectionSeeder;
use Kingletas\CatalogBatch\Model\Config;
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
    /** @var array<int, array{0: int, 1: int, 2: int}> Products, store and website of each seeding. */
    private array $seedings = [];

    public function testAListingIsSeededWithItsOwnStoreAndWebsite(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([1 => $this->createMock(Product::class)]);

        $this->listingObserver()->execute($this->observer(['collection' => $collection]));

        $this->assertSame([[1, 3, 4]], $this->seedings);
    }

    public function testTheSwitchBeingOffMeansNothingIsAsked(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([1 => $this->createMock(Product::class)]);

        $this->listingObserver(false)->execute($this->observer(['collection' => $collection]));

        $this->assertSame([], $this->seedings);
    }

    /**
     * Another module can dispatch this event with anything, so the observer has to check what it was handed.
     */
    public function testSomethingOtherThanAProductCollectionIsIgnored(): void
    {
        $this->listingObserver()->execute($this->observer(['collection' => new DataObject()]));

        $this->assertSame([], $this->seedings);
    }

    public function testAProductPageSeedsTheOneProductItIsShowing(): void
    {
        $this->viewObserver()->execute($this->observer(['product' => $this->createMock(Product::class)]));

        $this->assertSame([[1, 3, 4]], $this->seedings);
    }

    public function testAProductViewWithNoProductIsIgnored(): void
    {
        $this->viewObserver()->execute($this->observer([]));

        $this->assertSame([], $this->seedings);
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
        return new SeedListingConfigurables($this->config($enabled), $this->seeder(), $this->stores());
    }

    private function viewObserver(bool $enabled = true): SeedViewedConfigurable
    {
        return new SeedViewedConfigurable($this->config($enabled), $this->seeder(), $this->stores());
    }

    private function config(bool $enabled): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn($enabled);

        return $config;
    }

    private function seeder(): AttributeCollectionSeeder
    {
        $seeder = $this->createMock(AttributeCollectionSeeder::class);
        $seeder->method('seed')->willReturnCallback(
            function (array $products, int $storeId, int $websiteId): int {
                $this->seedings[] = [count($products), $storeId, $websiteId];

                return count($products);
            }
        );

        return $seeder;
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
