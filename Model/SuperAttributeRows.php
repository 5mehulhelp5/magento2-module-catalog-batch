<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\Store;

/**
 * The super attribute rows and their store labels, for every configurable on a page in one query.
 */
class SuperAttributeRows
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Magento runs one query for the rows and a second for the labels, once per product; this is both, once.
     *
     * @param int[] $parentLinkIds
     * @return array<int, array<int, array<string, mixed>>> Parent link id to super attribute id to its row.
     */
    public function forParents(array $parentLinkIds, int $storeId): array
    {
        if ($parentLinkIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $labels = $this->resourceConnection->getTableName('catalog_product_super_attribute_label');
        $select = $connection->select()
            ->from(
                ['main_table' => $this->resourceConnection->getTableName('catalog_product_super_attribute')],
                ['product_id', 'attribute_id', 'position', 'product_super_attribute_id']
            )
            ->joinLeft(
                ['def' => $labels],
                'def.product_super_attribute_id = main_table.product_super_attribute_id AND def.store_id = '
                . Store::DEFAULT_STORE_ID,
                []
            )
            ->joinLeft(
                ['store' => $labels],
                'store.product_super_attribute_id = main_table.product_super_attribute_id AND store.store_id = '
                . $storeId,
                [
                    'use_default' => $connection->getCheckSql(
                        'store.use_default IS NULL',
                        'def.use_default',
                        'store.use_default'
                    ),
                    'label' => $connection->getCheckSql('store.value IS NULL', 'def.value', 'store.value'),
                ]
            )
            ->where('main_table.product_id IN (?)', $parentLinkIds)
            ->order(['main_table.position ASC', 'main_table.attribute_id ASC']);

        $rows = [];

        foreach ($connection->fetchAll($select) as $row) {
            $rows[(int) $row['product_id']][(int) $row['product_super_attribute_id']] = [
                'product_super_attribute_id' => (int) $row['product_super_attribute_id'],
                'attribute_id' => (int) $row['attribute_id'],
                'position' => (int) $row['position'],
                'label' => $row['label'] === null ? null : (string) $row['label'],
                'use_default' => $row['use_default'] === null ? null : (int) $row['use_default'],
            ];
        }

        return $rows;
    }
}
