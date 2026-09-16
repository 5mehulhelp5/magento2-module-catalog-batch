<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model;

use Kingletas\CatalogBatch\Model\SeededAttributeCollection;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SeededAttributeCollectionTest extends TestCase
{
    /**
     * Every way Magento reaches into a collection runs load() first, and a seeded one must survive all of them.
     */
    public function testASeededCollectionIsReadableWithoutBeingLoaded(): void
    {
        $collection = $this->collection();
        $collection->seed([$this->attribute(11), $this->attribute(12)], 3);

        $this->assertTrue($collection->isLoaded());
        $this->assertCount(2, $collection);
        $this->assertSame([11, 12], array_keys($collection->getItems()));
        $this->assertSame(3, $collection->getStoreId());
        $this->assertNotNull($collection->getItemById(12));
    }

    private function collection(): SeededAttributeCollection
    {
        return (new ReflectionClass(SeededAttributeCollection::class))->newInstanceWithoutConstructor();
    }

    private function attribute(int $superAttributeId): Attribute
    {
        $attribute = $this->createMock(Attribute::class);
        $attribute->method('getId')->willReturn($superAttributeId);

        return $attribute;
    }
}
