<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Store\Model\Store;

/**
 * The option rows Magento's own query returns, for every configurable on a page at once instead of one at a time.
 */
class OptionRows
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LinkField $linkField,
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * @param int[] $parentLinkIds
     * @param array<int, int[]> $attributeIdsByParent
     * @return array<int, array<int, array<int, array<string, mixed>>>> Parent, then attribute id, then its rows.
     */
    public function forParents(
        array $parentLinkIds,
        array $attributeIdsByParent,
        int $storeId,
        int $websiteId
    ): array {
        $attributeIds = $this->uniqueIds($attributeIdsByParent);

        if ($parentLinkIds === [] || $attributeIds === []) {
            return [];
        }

        $rows = [];

        foreach ($this->backendTables($attributeIds) as $table => $ids) {
            $select = $this->optionSelect($parentLinkIds, $ids, (string) $table, $storeId, $websiteId);

            foreach ($this->resourceConnection->getConnection()->fetchAll($select) as $row) {
                $parent = (int) $row['parent_link_id'];
                $attributeId = (int) $row['attribute_id'];
                unset($row['parent_link_id'], $row['attribute_id']);
                $rows[$parent][$attributeId][] = $row;
            }
        }

        return $this->titledFromSources($rows, $attributeIds);
    }

    /**
     * @param array<int, int[]> $attributeIdsByParent
     * @return int[]
     */
    private function uniqueIds(array $attributeIdsByParent): array
    {
        return array_values(array_unique(array_merge([], ...array_values($attributeIdsByParent))));
    }

    /**
     * Super attributes are select attributes, so in practice this is one table and one query.
     *
     * @param int[] $attributeIds
     * @return array<string, int[]>
     */
    private function backendTables(array $attributeIds): array
    {
        $tables = [];

        foreach ($attributeIds as $attributeId) {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeId);
            $tables[(string) $attribute->getBackendTable()][$attributeId] = $attributeId;
        }

        return array_map('array_values', $tables);
    }

    /**
     * @param int[] $parentLinkIds
     * @param int[] $attributeIds
     */
    private function optionSelect(
        array $parentLinkIds,
        array $attributeIds,
        string $table,
        int $storeId,
        int $websiteId
    ): Select {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['super_attribute' => $this->resourceConnection->getTableName('catalog_product_super_attribute')],
                [
                    'parent_link_id' => 'super_attribute.product_id',
                    'attribute_id' => 'super_attribute.attribute_id',
                    'sku' => 'entity.sku',
                    'product_id' => 'product_entity.entity_id',
                    'attribute_code' => 'attribute.attribute_code',
                    'value_index' => 'entity_value.value',
                    'super_attribute_label' => 'attribute_label.value',
                    'option_title' => $connection->getIfNullSql('option_value.value', 'default_option_value.value'),
                    'default_title' => 'default_option_value.value',
                ]
            )
            ->order(['attribute_option.sort_order ASC', 'entity.entity_id ASC'])
            ->where('super_attribute.product_id IN (?)', $parentLinkIds)
            ->where('super_attribute.attribute_id IN (?)', $attributeIds);

        return $this->joinOptionTables($select, $table, $storeId, $websiteId);
    }

    private function joinOptionTables(Select $select, string $table, int $storeId, int $websiteId): Select
    {
        $link = $this->linkField->product();
        $entity = $this->resourceConnection->getTableName('catalog_product_entity');
        $optionValue = $this->resourceConnection->getTableName('eav_attribute_option_value');

        return $select
            ->joinInner(['product_entity' => $entity], "product_entity.{$link} = super_attribute.product_id", [])
            ->joinInner(
                ['product_link' => $this->resourceConnection->getTableName('catalog_product_super_link')],
                'product_link.parent_id = super_attribute.product_id',
                []
            )
            ->joinInner(
                ['attribute' => $this->resourceConnection->getTableName('eav_attribute')],
                'attribute.attribute_id = super_attribute.attribute_id',
                []
            )
            ->joinInner(['entity' => $entity], 'entity.entity_id = product_link.product_id', [])
            // Magento's own storefront plugin filters these rows to the website, so this query has to as well.
            ->joinInner(
                ['entity_website' => $this->resourceConnection->getTableName('catalog_product_website')],
                'entity_website.product_id = entity.entity_id AND entity_website.website_id = ' . $websiteId,
                []
            )
            ->joinInner(
                ['entity_value' => $table],
                implode(' AND ', [
                    'entity_value.attribute_id = super_attribute.attribute_id',
                    'entity_value.store_id = ' . Store::DEFAULT_STORE_ID,
                    "entity_value.{$link} = entity.{$link}",
                ]),
                []
            )
            ->joinLeft(
                ['attribute_label' => $this->resourceConnection->getTableName(
                    'catalog_product_super_attribute_label'
                )],
                implode(' AND ', [
                    'super_attribute.product_super_attribute_id = attribute_label.product_super_attribute_id',
                    'attribute_label.store_id = ' . Store::DEFAULT_STORE_ID,
                ]),
                []
            )
            ->joinLeft(
                ['attribute_option' => $this->resourceConnection->getTableName('eav_attribute_option')],
                'attribute_option.option_id = entity_value.value',
                []
            )
            ->joinLeft(
                ['option_value' => $optionValue],
                'option_value.option_id = entity_value.value AND option_value.store_id = ' . $storeId,
                []
            )
            ->joinLeft(
                ['default_option_value' => $optionValue],
                'default_option_value.option_id = entity_value.value AND default_option_value.store_id = '
                . Store::DEFAULT_STORE_ID,
                []
            );
    }

    /**
     * An attribute with a source model takes its titles from the source, which is what Magento's own provider does.
     *
     * @param array<int, array<int, array<int, array<string, mixed>>>> $rows
     * @param int[] $attributeIds
     * @return array<int, array<int, array<int, array<string, mixed>>>>
     */
    private function titledFromSources(array $rows, array $attributeIds): array
    {
        foreach ($attributeIds as $attributeId) {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeId);

            if (!$attribute->getSourceModel()) {
                continue;
            }

            $labels = $this->sourceLabels($attribute);

            foreach ($rows as $parent => $byAttribute) {
                foreach ($byAttribute[$attributeId] ?? [] as $key => $row) {
                    $title = $labels[$row['value_index']] ?? false;
                    $row['default_title'] = $title;
                    $row['option_title'] = $title;
                    $rows[$parent][$attributeId][$key] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @return array<int|string, string>
     */
    private function sourceLabels(AbstractAttribute $attribute): array
    {
        $labels = [];

        foreach ($attribute->getSource()->getAllOptions() as $option) {
            $labels[$option['value']] = $option['label'];
        }

        return $labels;
    }
}
