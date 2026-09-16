<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model;

use Kingletas\CatalogBatch\Model\AttributeCollectionSeeder;
use Kingletas\CatalogBatch\Model\Config;
use Kingletas\CatalogBatch\Model\OptionRows;
use Kingletas\CatalogBatch\Model\SeededAttributeCollection;
use Kingletas\CatalogBatch\Model\SeededAttributeCollectionFactory;
use Kingletas\CatalogBatch\Model\SuperAttributeRows;
use Kingletas\CatalogBatch\Test\Support\LinkFieldDouble;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttribute;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\AttributeFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

class AttributeCollectionSeederTest extends TestCase
{
    use LinkFieldDouble;

    private const string CACHE_KEY = '_cache_instance_configurable_attributes';

    /** @var array<int, array<int, Attribute>> Each collection the seeder built, in the order it built them. */
    private array $seeded = [];

    /** @var array<int, int[]> Every set of parents the row queries were asked about. */
    private array $asked = [];

    public function testEveryConfigurableOnThePageIsAskedAboutOnce(): void
    {
        $products = [$this->product(5, 'configurable'), $this->product(6, 'configurable')];

        $count = $this->seeder()->seed($products, 3, 1);

        $this->assertSame(2, $count);
        $this->assertSame([[5, 6], [5, 6]], $this->asked);
        $this->assertInstanceOf(SeededAttributeCollection::class, $products[0]->getData(self::CACHE_KEY));
        $this->assertInstanceOf(SeededAttributeCollection::class, $products[1]->getData(self::CACHE_KEY));
    }

    public function testASimpleProductIsLeftAlone(): void
    {
        $products = [$this->product(5, 'simple')];

        $this->assertSame(0, $this->seeder()->seed($products, 3, 1));
        $this->assertSame([], $this->asked);
        $this->assertNull($products[0]->getData(self::CACHE_KEY));
    }

    /**
     * Magento short-circuits on this key, so a product that already has it has already been answered.
     */
    public function testAProductThatAlreadyHasItsAttributesIsNotAskedAboutAgain(): void
    {
        $product = $this->product(5, 'configurable');
        $product->setData(self::CACHE_KEY, 'whatever Magento put here');

        $this->assertSame(0, $this->seeder()->seed([$product], 3, 1));
        $this->assertSame([], $this->asked);
    }

    public function testTheSeededAttributesCarryTheLabelAndTheOptionMap(): void
    {
        $this->seeder()->seed([$this->product(5, 'configurable')], 3, 1);

        $attribute = $this->seeded[0][0];
        $this->assertSame(11, $attribute->getData('product_super_attribute_id'));
        $this->assertSame('Colour', $attribute->getData('label'));
        // The swatch JSON carries this verbatim, so a number here would change what the front end compares.
        $this->assertSame('0', $attribute->getData('position'));
        $this->assertSame(5, $attribute->getData('product_id'));
        $this->assertSame([
            [
                'value_index' => '52',
                'label' => 'Blue',
                'product_super_attribute_id' => 11,
                'default_label' => 'Blue default',
                'store_label' => 'Blue default',
                'use_default_value' => true,
            ],
        ], $attribute->getData('options'));
    }

    /**
     * A page wider than the batch size is covered by several queries rather than one that grows without a bound.
     */
    public function testAPageWiderThanTheBatchSizeIsAskedAboutInChunks(): void
    {
        $products = [$this->product(5, 'configurable'), $this->product(6, 'configurable')];

        $this->seeder(1)->seed($products, 3, 1);

        $this->assertSame([[5], [5], [6], [6]], $this->asked);
    }

    public function testAParentTheQueryKnowsNothingAboutIsLeftToMagento(): void
    {
        $products = [$this->product(9, 'configurable')];

        $this->assertSame(0, $this->seeder()->seed($products, 3, 1));
        $this->assertNull($products[0]->getData(self::CACHE_KEY));
    }

    private function seeder(int $batchSize = 100): AttributeCollectionSeeder
    {
        $superAttributes = $this->createMock(SuperAttributeRows::class);
        $superAttributes->method('forParents')->willReturnCallback(
            function (array $parents): array {
                $this->asked[] = $parents;

                return array_reduce($parents, static function (array $rows, int $parent): array {
                    if ($parent === 9) {
                        return $rows;
                    }

                    $rows[$parent] = [
                        11 => [
                            'product_super_attribute_id' => 11,
                            'attribute_id' => 93,
                            'position' => 0,
                            'label' => 'Colour',
                            'use_default' => 0,
                        ],
                    ];

                    return $rows;
                }, []);
            }
        );

        $optionRows = $this->createMock(OptionRows::class);
        $optionRows->method('forParents')->willReturnCallback(
            function (array $parents): array {
                $this->asked[] = $parents;

                return array_reduce($parents, static function (array $rows, int $parent): array {
                    $rows[$parent] = [
                        93 => [
                            ['value_index' => '52', 'option_title' => 'Blue', 'default_title' => 'Blue default'],
                        ],
                    ];

                    return $rows;
                }, []);
            }
        );

        $eavAttribute = $this->createMock(EavAttribute::class);
        $eavAttribute->method('getId')->willReturn(93);
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->willReturn($eavAttribute);

        $attributeFactory = $this->createMock(AttributeFactory::class);
        $attributeFactory->method('create')->willReturnCallback(fn (): Attribute => $this->attribute());

        $collectionFactory = $this->createMock(SeededAttributeCollectionFactory::class);
        $collectionFactory->method('create')->willReturnCallback(
            function (): SeededAttributeCollection {
                $collection = $this->createMock(SeededAttributeCollection::class);
                $collection->method('seed')->willReturnCallback(
                    function (array $attributes): void {
                        $this->seeded[] = array_values($attributes);
                    }
                );

                return $collection;
            }
        );

        $config = $this->createMock(Config::class);
        $config->method('getBatchSize')->willReturn($batchSize);

        return new AttributeCollectionSeeder(
            $superAttributes,
            $optionRows,
            $collectionFactory,
            $attributeFactory,
            $eavConfig,
            $this->linkField(),
            $config
        );
    }

    private function product(int $id, string $type): Product
    {
        $product = $this->createMock(Product::class);
        $data = new DataObject(['entity_id' => $id]);
        $product->method('getTypeId')->willReturn($type);
        $product->method('getData')->willReturnCallback(
            fn (string $key = '', mixed $index = null): mixed => $data->getData($key, $index)
        );
        $product->method('setData')->willReturnCallback(
            function (array|string $key, mixed $value = null) use ($data, $product): Product {
                $data->setData($key, $value);

                return $product;
            }
        );
        $product->method('hasData')->willReturnCallback(
            fn (string $key = ''): bool => $data->hasData($key)
        );

        return $product;
    }

    private function attribute(): Attribute
    {
        $attribute = $this->createMock(Attribute::class);
        $data = new DataObject();
        $attribute->method('setData')->willReturnCallback(
            function (array|string $key, mixed $value = null) use ($data, $attribute): Attribute {
                $data->setData($key, $value);

                return $attribute;
            }
        );
        $attribute->method('getData')->willReturnCallback(
            fn (string $key = '', mixed $index = null): mixed => $data->getData($key, $index)
        );

        return $attribute;
    }
}
