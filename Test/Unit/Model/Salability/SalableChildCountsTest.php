<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model\Salability;

use Kingletas\CatalogBatch\Model\Salability\SalableChildCounts;
use Kingletas\CatalogBatch\Test\Support\StubbedDatabase;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Collection\SalableProcessor;
use PHPUnit\Framework\TestCase;

class SalableChildCountsTest extends TestCase
{
    use StubbedDatabase;

    /** @var int[] Whatever the collection was filtered to. */
    private array $filtered = [];

    private bool $storeFiltered = false;

    private bool $processed = false;

    /**
     * Magento counts one product at a time; every parent on the page has to be covered by one pass.
     */
    public function testEveryParentIsCountedFromOneCollection(): void
    {
        $this->answers['catalog_product_super_link'] = [
            ['parent_id' => '5', 'product_id' => '100'],
            ['parent_id' => '5', 'product_id' => '101'],
            ['parent_id' => '6', 'product_id' => '102'],
        ];

        $counts = $this->counts([100, 102])->forParents([5, 6], 1);

        $this->assertSame([5 => 1, 6 => 1], $counts);
        $this->assertSame([100, 101, 102], $this->filtered);
        $this->assertTrue($this->storeFiltered);
        $this->assertTrue($this->processed);
    }

    public function testAParentWithNoBuyableChildCountsZeroRatherThanVanishing(): void
    {
        $this->answers['catalog_product_super_link'] = [['parent_id' => '5', 'product_id' => '100']];

        $this->assertSame([5 => 0], $this->counts([])->forParents([5], 1));
    }

    public function testNoParentsMeansNoQueryAtAll(): void
    {
        $this->assertSame([], $this->counts([])->forParents([], 1));
        $this->assertSame([], $this->queries);
    }

    public function testAParentWithNoChildrenIsNotCounted(): void
    {
        $this->answers['catalog_product_super_link'] = [];

        $this->assertSame([], $this->counts([])->forParents([5], 1));
    }

    /**
     * @param int[] $salable
     */
    private function counts(array $salable): SalableChildCounts
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addIdFilter')->willReturnCallback(
            function (mixed $ids) use ($collection): Collection {
                $this->filtered = array_map('intval', (array) $ids);

                return $collection;
            }
        );
        $collection->method('addStoreFilter')->willReturnCallback(
            function () use ($collection): Collection {
                $this->storeFiltered = true;

                return $collection;
            }
        );
        $collection->method('getAllIds')->willReturn($salable);

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $processor = $this->createMock(SalableProcessor::class);
        $processor->method('process')->willReturnCallback(
            function (Collection $given) use ($collection): Collection {
                $this->processed = true;

                return $collection;
            }
        );

        return new SalableChildCounts($this->resourceConnection(), $factory, $processor);
    }
}
