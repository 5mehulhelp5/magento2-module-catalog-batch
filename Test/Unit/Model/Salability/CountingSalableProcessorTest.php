<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model\Salability;

use Kingletas\CatalogBatch\Model\Config;
use Kingletas\CatalogBatch\Model\Salability\CountedChildCollection;
use Kingletas\CatalogBatch\Model\Salability\CountingSalableProcessor;
use Kingletas\CatalogBatch\Model\Salability\SalableChildCounter;
use Kingletas\CatalogBatch\Model\Salability\ServedSalableCounts;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status;
use Magento\CatalogInventory\Model\ResourceModel\Stock\StatusFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

class CountingSalableProcessorTest extends TestCase
{
    private ServedSalableCounts $served;

    /** @var list<int|null> Every size the processor handed over. */
    private array $answered = [];

    private int $stockFilters = 0;

    /** @var list<array{0: int, 1: int}> Parent and store each on-demand count was asked for. */
    private array $countedOnDemand = [];

    protected function setUp(): void
    {
        $this->served = new ServedSalableCounts();
        $this->served->remember(7, 3, 2);
    }

    public function testACountedParentIsAnsweredFromThePageCount(): void
    {
        $this->processor()->process($this->childCollection(7, 3));

        $this->assertSame([2], $this->answered);
    }

    /**
     * Magento's own filters are still applied, so a caller that loads the items loads exactly what it would have.
     */
    public function testMagentosFiltersAreAppliedEitherWay(): void
    {
        $this->processor()->process($this->childCollection(7, 3));
        $this->processor()->process($this->childCollection(9, 3));

        $this->assertSame(2, $this->stockFilters);
    }

    public function testAParentNotYetCountedHasItsListingCountedThenAnswered(): void
    {
        $this->processor()->process($this->childCollection(8, 3));

        $this->assertSame([[8, 3]], $this->countedOnDemand);
        $this->assertSame([5], $this->answered);
    }

    public function testAParentNoListingNotedIsLeftToMagento(): void
    {
        $this->processor()->process($this->childCollection(9, 3));

        $this->assertSame([[9, 3]], $this->countedOnDemand);
        $this->assertSame([], $this->answered);
    }

    public function testACountFromAnotherStoreIsNotUsed(): void
    {
        $this->processor()->process($this->childCollection(7, 4));
        $this->processor()->process($this->childCollection(7, null));

        $this->assertSame([], $this->answered);
    }

    public function testTheSwitchBeingOffLeavesEveryCountToMagento(): void
    {
        $this->processor(false)->process($this->childCollection(7, 3));

        $this->assertSame([], $this->answered);
    }

    /**
     * A collection filtered by store object reports that store's id, so its count is answered like any other.
     */
    public function testACollectionFilteredByAStoreObjectIsAnswered(): void
    {
        $this->processor()->process($this->childCollection(7, $this->filteredByObject(3)));

        $this->assertSame([2], $this->answered);
    }

    /**
     * Any other product collection passes through as Magento's processor would pass it.
     */
    public function testAnOrdinaryCollectionIsOnlyFiltered(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('hasFlag')->willReturn(false);

        $this->assertSame($collection, $this->processor()->process($collection));
        $this->assertSame(1, $this->stockFilters);
    }

    private function processor(bool $enabled = true): CountingSalableProcessor
    {
        $status = $this->createMock(Status::class);
        $status->method('addStockDataToCollection')->willReturnCallback(function (): void {
            $this->stockFilters++;
        });
        $factory = $this->createMock(StatusFactory::class);
        $factory->method('create')->willReturn($status);

        $config = $this->createMock(Config::class);
        $config->method('isSalabilityEnabled')->willReturn($enabled);

        $counter = $this->createMock(SalableChildCounter::class);
        $counter->method('countGroupOf')->willReturnCallback(function (int $parentId, int $storeId): int {
            $this->countedOnDemand[] = [$parentId, $storeId];

            if ($parentId !== 8) {
                return 0;
            }

            $this->served->remember(8, $storeId, 5);

            return 1;
        });

        return new CountingSalableProcessor($factory, $config, $this->served, $counter);
    }

    /**
     * The store id a real counted collection reports after addStoreFilter() is given a store object.
     */
    private function filteredByObject(int $storeId): ?int
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($this->createConfiguredMock(StoreInterface::class, ['getId' => 0]));
        $collection = (new ReflectionClass(CountedChildCollection::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(get_parent_class(CountedChildCollection::class) ?: '', '_storeManager'))
            ->setValue($collection, $stores);
        $collection->addStoreFilter($store);

        return $collection->getFilteredStoreId();
    }

    private function childCollection(int $parentId, ?int $storeId): CountedChildCollection
    {
        $collection = $this->createMock(CountedChildCollection::class);
        $collection->method('hasFlag')->willReturn(false);
        $collection->method('getSoleParentId')->willReturn($parentId);
        $collection->method('getFilteredStoreId')->willReturn($storeId);
        $collection->method('answerSizeWith')->willReturnCallback(function (int $count): void {
            $this->answered[] = $count;
        });

        return $collection;
    }
}
