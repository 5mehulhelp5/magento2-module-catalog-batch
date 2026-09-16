<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\AttributeFactory;
use Magento\Eav\Model\Config as EavConfig;

/**
 * Answers a page's configurable attribute queries once and hands each product the answer it would have asked for.
 */
class AttributeCollectionSeeder
{
    private const string CONFIGURABLE_ATTRIBUTES = '_cache_instance_configurable_attributes';

    public function __construct(
        private readonly SuperAttributeRows $superAttributes,
        private readonly OptionRows $optionRows,
        private readonly SeededAttributeCollectionFactory $collectionFactory,
        private readonly AttributeFactory $attributeFactory,
        private readonly EavConfig $eavConfig,
        private readonly LinkField $linkField,
        private readonly Config $config
    ) {
    }

    /**
     * @param Product[] $products
     * @return int How many products were given their attributes, so a caller can say whether it did anything.
     */
    public function seed(array $products, int $storeId, int $websiteId): int
    {
        $parents = $this->parents($products);

        if ($parents === []) {
            return 0;
        }

        $seeded = 0;

        foreach (array_chunk($parents, $this->config->getBatchSize(), true) as $chunk) {
            $seeded += $this->seedChunk($chunk, $storeId, $websiteId);
        }

        return $seeded;
    }

    /**
     * @param array<int, Product> $chunk Parent link id to the product carrying it.
     */
    private function seedChunk(array $chunk, int $storeId, int $websiteId): int
    {
        $linkIds = array_keys($chunk);
        $superAttributes = $this->superAttributes->forParents($linkIds, $storeId);

        if ($superAttributes === []) {
            return 0;
        }

        $options = $this->optionRows->forParents(
            $linkIds,
            array_map(static fn (array $rows): array => array_column($rows, 'attribute_id'), $superAttributes),
            $storeId,
            $websiteId
        );
        $seeded = 0;

        foreach ($chunk as $linkId => $product) {
            $collection = $this->collection(
                $superAttributes[$linkId] ?? [],
                $options[$linkId] ?? [],
                $linkId,
                $storeId
            );

            if ($collection === null) {
                continue;
            }

            $product->setData(self::CONFIGURABLE_ATTRIBUTES, $collection);
            $seeded++;
        }

        return $seeded;
    }

    /**
     * @param Product[] $products
     * @return array<int, Product> Parent link id to product, for the configurables that still need their attributes.
     */
    private function parents(array $products): array
    {
        $link = $this->linkField->product();
        $parents = [];

        foreach ($products as $product) {
            $linkId = (int) $product->getData($link);

            if ($product->getTypeId() !== Configurable::TYPE_CODE
                || $linkId === 0
                || $product->hasData(self::CONFIGURABLE_ATTRIBUTES)
            ) {
                continue;
            }

            $parents[$linkId] = $product;
        }

        return $parents;
    }

    /**
     * Null when a row cannot be turned into an attribute, because half a collection renders half the swatches.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<int, array<string, mixed>>> $options
     */
    private function collection(
        array $rows,
        array $options,
        int $linkId,
        int $storeId
    ): ?SeededAttributeCollection {
        if ($rows === []) {
            return null;
        }

        $attributes = [];

        foreach ($rows as $row) {
            $attribute = $this->attribute($row, $options[(int) $row['attribute_id']] ?? [], $linkId);

            if ($attribute === null) {
                return null;
            }

            $attributes[] = $attribute;
        }

        $collection = $this->collectionFactory->create();
        $collection->seed($attributes, $storeId);

        return $collection;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array<string, mixed>> $optionRows
     */
    private function attribute(array $row, array $optionRows, int $linkId): ?Attribute
    {
        $eavAttribute = $this->eavConfig->getAttribute(Product::ENTITY, (int) $row['attribute_id']);

        if (!$eavAttribute->getId()) {
            return null;
        }

        $superAttributeId = (int) $row['product_super_attribute_id'];
        $options = $this->options($optionRows, $superAttributeId);
        $attribute = $this->attributeFactory->create();
        $attribute->setData([
            'product_super_attribute_id' => $superAttributeId,
            'product_id' => $linkId,
            'attribute_id' => (int) $row['attribute_id'],
            // The database hands these back as strings and the swatch JSON carries them verbatim, so a
            // seeded collection has to as well or a strict comparison in the front end stops matching.
            'position' => (string) $row['position'],
            'label' => $row['label'],
            'use_default' => isset($row['use_default']) ? (string) $row['use_default'] : null,
            'product_attribute' => $eavAttribute,
            'options_map' => $options,
            'options' => array_values($options),
        ]);

        return $attribute;
    }

    /**
     * The same map Magento builds in its own loadOptions, from the same rows its own option query returns.
     *
     * @param array<int, array<string, mixed>> $optionRows
     * @return array<string, array<string, mixed>>
     */
    private function options(array $optionRows, int $superAttributeId): array
    {
        $values = [];

        foreach ($optionRows as $option) {
            $values[$superAttributeId . ':' . $option['value_index']] = [
                'value_index' => $option['value_index'],
                'label' => $option['option_title'],
                'product_super_attribute_id' => $superAttributeId,
                'default_label' => $option['default_title'],
                'store_label' => $option['default_title'],
                'use_default_value' => true,
            ];
        }

        return $values;
    }
}
