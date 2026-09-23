<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model\Salability;

use Kingletas\CatalogBatch\Model\Config;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogInventory\Model\ResourceModel\Stock\StatusFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Collection\SalableProcessor;

/**
 * Magento's salable filter for a configurable's children, which also hands over the count the page works out at once.
 */
class CountingSalableProcessor extends SalableProcessor
{
    public function __construct(
        StatusFactory $stockStatusFactory,
        private readonly Config $config,
        private readonly ServedSalableCounts $served,
        private readonly SalableChildCounter $counter
    ) {
        parent::__construct($stockStatusFactory);
    }

    /**
     * The filters are applied either way, so a caller that loads the items gets exactly what Magento would load.
     */
    public function process(Collection $collection): Collection
    {
        $collection = parent::process($collection);

        if ($collection instanceof CountedChildCollection) {
            $count = $this->servedCount($collection);

            if ($count !== null) {
                $collection->answerSizeWith($count);
            }
        }

        return $collection;
    }

    private function servedCount(CountedChildCollection $collection): ?int
    {
        $parentId = $collection->getSoleParentId();
        $storeId = $collection->getFilteredStoreId();

        if ($parentId === null || $storeId === null || !$this->config->isSalabilityEnabled($storeId)) {
            return null;
        }

        $count = $this->served->forProduct($parentId, $storeId);

        if ($count === null && $this->counter->countGroupOf($parentId, $storeId) > 0) {
            $count = $this->served->forProduct($parentId, $storeId);
        }

        return $count;
    }
}
