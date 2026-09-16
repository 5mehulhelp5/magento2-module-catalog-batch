<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Performance;

use Kingletas\CatalogBatch\Model\AttributeCollectionSeeder;
use Kingletas\CatalogBatch\Model\Config;
use Kingletas\CatalogBatch\Model\OptionRows;
use Kingletas\CatalogBatch\Model\SeededAttributeCollection;
use Kingletas\CatalogBatch\Model\SeededAttributeCollectionFactory;
use Kingletas\CatalogBatch\Model\SuperAttributeRows;
use Kingletas\CatalogBatch\Test\Support\LinkFieldDouble;
use Kingletas\CatalogBatch\Test\Support\StubbedDatabase;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttribute;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\AttributeFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\Source\SourceInterface;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

/**
 * The whole point of this module is that a page's cost stops tracking the number of products on it.
 */
class BatchCostTest extends TestCase
{
    use LinkFieldDouble;
    use StubbedDatabase;

    /**
     * Magento asks three times per configurable. A listing of two and a listing of twenty must cost the same.
     */
    public function testTheQueryCountDoesNotGrowWithTheListing(): void
    {
        $this->assertSame(2, $this->queriesForAListingOf(2));

        $this->queries = [];

        $this->assertSame(2, $this->queriesForAListingOf(20));
    }

    private function queriesForAListingOf(int $products): int
    {
        $this->seeder()->seed($this->products($products), 3, 1);

        return count($this->queries);
    }

    /**
     * @return Product[]
     */
    private function products(int $count): array
    {
        $products = [];

        for ($id = 1; $id <= $count; $id++) {
            $product = $this->createMock(Product::class);
            $data = new DataObject(['entity_id' => $id]);
            $product->method('getTypeId')->willReturn('configurable');
            $product->method('getData')->willReturnCallback(
                fn (string $key = '', mixed $index = null): mixed => $data->getData($key, $index)
            );
            $product->method('hasData')->willReturnCallback(fn (string $key = ''): bool => $data->hasData($key));
            $product->method('setData')->willReturnCallback(
                function (array|string $key, mixed $value = null) use ($data, $product): Product {
                    $data->setData($key, $value);

                    return $product;
                }
            );
            $products[] = $product;
        }

        return $products;
    }

    private function seeder(): AttributeCollectionSeeder
    {
        $resource = $this->resourceConnection();
        $this->answers['catalog_product_super_attribute'] = function (array $query): array {
            $parents = [];

            foreach ($query['calls'] as [$method, $args]) {
                if ($method === 'where' && str_contains((string) ($args[0] ?? ''), 'product_id IN')) {
                    $parents = (array) ($args[1] ?? []);
                }
            }

            return array_map(static fn (int $parent): array => [
                'product_id' => (string) $parent,
                'attribute_id' => '93',
                'position' => '0',
                'product_super_attribute_id' => (string) (10 + $parent),
                'label' => 'Colour',
                'use_default' => '0',
                'parent_link_id' => (string) $parent,
                'sku' => 'child',
                'attribute_code' => 'color',
                'value_index' => '52',
                'super_attribute_label' => 'Color',
                'option_title' => 'Blue',
                'default_title' => 'Blue',
            ], $parents);
        };

        $source = $this->createMock(SourceInterface::class);
        $source->method('getAllOptions')->willReturn([]);
        $eavAttribute = $this->createMock(EavAttribute::class);
        $eavAttribute->method('getId')->willReturn(93);
        $eavAttribute->method('getBackendTable')->willReturn('catalog_product_entity_int');
        $eavAttribute->method('getSourceModel')->willReturn(null);
        $eavAttribute->method('getSource')->willReturn($source);
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->willReturn($eavAttribute);

        $attributeFactory = $this->createMock(AttributeFactory::class);
        $attributeFactory->method('create')->willReturnCallback(
            fn (): Attribute => $this->createMock(Attribute::class)
        );

        $collectionFactory = $this->createMock(SeededAttributeCollectionFactory::class);
        $collectionFactory->method('create')->willReturnCallback(
            fn (): SeededAttributeCollection => $this->createMock(SeededAttributeCollection::class)
        );

        $config = $this->createMock(Config::class);
        $config->method('getBatchSize')->willReturn(100);

        return new AttributeCollectionSeeder(
            new SuperAttributeRows($resource),
            new OptionRows($resource, $this->linkField(), $eavConfig),
            $collectionFactory,
            $attributeFactory,
            $eavConfig,
            $this->linkField(),
            $config
        );
    }
}
