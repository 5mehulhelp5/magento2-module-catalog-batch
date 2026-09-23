<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Performance;

use Kingletas\CatalogBatch\Model\Config;
use Kingletas\CatalogBatch\Model\Salability\CountedChildCollection;
use Kingletas\CatalogBatch\Model\Salability\CountingSalableProcessor;
use Kingletas\CatalogBatch\Model\Salability\PendingSalableParents;
use Kingletas\CatalogBatch\Model\Salability\SalableChildCounter;
use Kingletas\CatalogBatch\Model\Salability\SalableChildCounts;
use Kingletas\CatalogBatch\Model\Salability\ServedSalableCounts;
use Kingletas\CatalogBatch\Test\Support\LinkFieldDouble;
use Kingletas\CatalogBatch\Test\Support\StubbedDatabase;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status;
use Magento\CatalogInventory\Model\ResourceModel\Stock\StatusFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Collection\SalableProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Magento counts each configurable's buyable children with a query each; with the switch on, a listing pays two.
 */
class SalableCountCostTest extends TestCase
{
    use LinkFieldDouble;
    use StubbedDatabase;

    private int $childCountQueries = 0;

    private int $salableIdQueries = 0;

    public function testWithTheSwitchOffEveryConfigurableCostsOneQuery(): void
    {
        $this->assertSame(2, $this->queriesForAListingOf(2, false));
        $this->assertSame(20, $this->queriesForAListingOf(20, false));
    }

    public function testWithTheSwitchOnTheQueryCountDoesNotGrowWithTheListing(): void
    {
        $this->assertSame(2, $this->queriesForAListingOf(2, true));
        $this->assertSame(2, $this->queriesForAListingOf(20, true));
    }

    /**
     * A listing loaded and never counted, like a related-products block nobody renders, costs nothing.
     */
    public function testAListingNobodyAsksAboutCostsNothing(): void
    {
        $this->queries = [];
        $this->salableIdQueries = 0;
        $config = $this->createMock(Config::class);
        $config->method('isSalabilityEnabled')->willReturn(true);
        $counter = new SalableChildCounter(
            $config,
            $this->counts(),
            new ServedSalableCounts(),
            new PendingSalableParents(),
            $this->linkField()
        );

        $counter->remember($this->products(20), 1);

        $this->assertSame(0, count($this->queries) + $this->salableIdQueries);
    }

    /**
     * Loads the listing, then asks each product what Configurable::isSalable asks: the size of its processed children.
     */
    private function queriesForAListingOf(int $count, bool $enabled): int
    {
        $this->queries = [];
        $this->childCountQueries = 0;
        $this->salableIdQueries = 0;
        $this->answers['catalog_product_super_link'] = $this->links($count);

        $config = $this->createMock(Config::class);
        $config->method('isSalabilityEnabled')->willReturn($enabled);
        $config->method('getBatchSize')->willReturn(100);
        $served = new ServedSalableCounts();
        $products = $this->products($count);

        $counter = new SalableChildCounter(
            $config,
            $this->counts(),
            $served,
            new PendingSalableParents(),
            $this->linkField()
        );
        $counter->remember($products, 1);
        $processor = new CountingSalableProcessor($this->stockStatus(), $config, $served, $counter);

        foreach ($products as $product) {
            $processor->process($this->childCollection((int) $product->getId()))->getSize();
        }

        return count($this->queries) + $this->salableIdQueries + $this->childCountQueries;
    }

    private function childCollection(int $parentId): CountedChildCollection
    {
        $answered = null;
        $collection = $this->createMock(CountedChildCollection::class);
        $collection->method('hasFlag')->willReturn(false);
        $collection->method('getSoleParentId')->willReturn($parentId);
        $collection->method('getFilteredStoreId')->willReturn(1);
        $collection->method('answerSizeWith')->willReturnCallback(function (int $size) use (&$answered): void {
            $answered = $size;
        });
        $collection->method('getSize')->willReturnCallback(function () use (&$answered): int {
            if ($answered === null) {
                $this->childCountQueries++;

                return 1;
            }

            return $answered;
        });

        return $collection;
    }

    private function counts(): SalableChildCounts
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addIdFilter')->willReturnSelf();
        $collection->method('addStoreFilter')->willReturnSelf();
        $collection->method('getAllIds')->willReturnCallback(function (): array {
            $this->salableIdQueries++;

            return [];
        });
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $processor = $this->createMock(SalableProcessor::class);
        $processor->method('process')->willReturnArgument(0);

        return new SalableChildCounts($this->resourceConnection(), $factory, $processor);
    }

    private function stockStatus(): StatusFactory
    {
        $factory = $this->createMock(StatusFactory::class);
        $factory->method('create')->willReturn($this->createMock(Status::class));

        return $factory;
    }

    /**
     * @return list<array{parent_id: string, product_id: string}>
     */
    private function links(int $count): array
    {
        $rows = [];

        for ($id = 1; $id <= $count; $id++) {
            $rows[] = ['parent_id' => (string) $id, 'product_id' => (string) (1000 + $id)];
        }

        return $rows;
    }

    /**
     * @return Product[]
     */
    private function products(int $count): array
    {
        $products = [];

        for ($id = 1; $id <= $count; $id++) {
            $product = $this->createMock(Product::class);
            $product->method('getId')->willReturn($id);
            $product->method('getTypeId')->willReturn('configurable');
            $product->method('getData')->willReturnCallback(
                static fn (string $key = ''): mixed => $key === 'entity_id' ? $id : null
            );
            $products[] = $product;
        }

        return $products;
    }
}
