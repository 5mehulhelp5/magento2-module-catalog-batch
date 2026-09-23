<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model\Salability;

use Kingletas\CatalogBatch\Model\Salability\CountedChildCollection;
use Magento\Catalog\Model\Product;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

class CountedChildCollectionTest extends TestCase
{
    public function testTheOneParentItIsFilteredToIsKnown(): void
    {
        $collection = $this->collection();
        $collection->setProductFilter($this->product(7));

        $this->assertSame(7, $collection->getSoleParentId());
    }

    /**
     * A collection covering several parents counts all their children together, so no one parent's count fits it.
     */
    public function testSeveralParentsOrNoneHaveNoSoleParent(): void
    {
        $this->assertNull($this->collection()->getSoleParentId());

        $collection = $this->collection();
        $collection->setProductFilter($this->product(7));
        $collection->setProductFilter($this->product(8));

        $this->assertNull($collection->getSoleParentId());
    }

    public function testTheStoreItIsFilteredToIsKnownOnlyWhenNamed(): void
    {
        $named = $this->collection();
        $named->addStoreFilter(3);
        $this->assertSame(3, $named->getFilteredStoreId());

        $current = $this->collection();
        $current->addStoreFilter();
        $this->assertNull($current->getFilteredStoreId());
    }

    /**
     * The type model can filter by the store object its product carries, so a store given that way is known too.
     */
    public function testAStoreGivenAsAnObjectIsKnownByItsId(): void
    {
        $collection = $this->collection();
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(3);

        $collection->addStoreFilter($store);

        $this->assertSame(3, $collection->getFilteredStoreId());
    }

    /**
     * Being told its size means getSize() answers without reaching the database at all.
     */
    public function testAnAnsweredSizeIsReturnedWithoutAQuery(): void
    {
        $collection = $this->collection();
        $collection->answerSizeWith(4);

        $this->assertSame(4, $collection->getSize());
    }

    /**
     * The constructor is Magento's own and needs a database, so the collection is built without it and given a store.
     */
    private function collection(): CountedChildCollection
    {
        $collection = (new ReflectionClass(CountedChildCollection::class))->newInstanceWithoutConstructor();
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(0);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $property = new ReflectionProperty(get_parent_class(CountedChildCollection::class) ?: '', '_storeManager');
        $property->setValue($collection, $stores);

        return $collection;
    }

    private function product(int $id): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }
}
