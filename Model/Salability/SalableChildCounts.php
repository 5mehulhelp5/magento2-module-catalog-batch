<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model\Salability;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Collection\SalableProcessor;
use Magento\Framework\App\ResourceConnection;

/**
 * How many children of each configurable anyone can buy, for a whole page instead of one product at a time.
 */
class SalableChildCounts
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CollectionFactory $collectionFactory,
        private readonly SalableProcessor $salableProcessor
    ) {
    }

    /**
     * Magento's own processor does the filtering, so whichever inventory module is installed still decides.
     *
     * @param int[] $parentLinkIds
     * @return array<int, int> Parent link id to how many of its children can be bought.
     */
    public function forParents(array $parentLinkIds, int $storeId): array
    {
        $children = $this->children($parentLinkIds);

        if ($children === []) {
            return [];
        }

        $childIds = array_values(array_unique(array_merge([], ...array_values($children))));
        $salable = $this->salableChildIds($childIds, $storeId);
        $counts = [];

        foreach ($children as $parent => $childIds) {
            $counts[$parent] = count(array_intersect($childIds, $salable));
        }

        return $counts;
    }

    /**
     * @param int[] $parentLinkIds
     * @return array<int, int[]>
     */
    private function children(array $parentLinkIds): array
    {
        if ($parentLinkIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('catalog_product_super_link'),
                    ['parent_id', 'product_id']
                )
                ->where('parent_id IN (?)', $parentLinkIds)
        );
        $children = [];

        foreach ($rows as $row) {
            $children[(int) $row['parent_id']][] = (int) $row['product_id'];
        }

        return $children;
    }

    /**
     * @param int[] $childIds
     * @return int[]
     */
    private function salableChildIds(array $childIds, int $storeId): array
    {
        if ($childIds === []) {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->setFlag('product_children', true);
        $collection->addIdFilter($childIds);
        $collection->addStoreFilter($storeId);

        return array_map('intval', $this->salableProcessor->process($collection)->getAllIds());
    }
}
