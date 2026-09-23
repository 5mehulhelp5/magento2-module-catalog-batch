<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model\Salability;

use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\Collection;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Magento's collection of a configurable's children, which can be told its size so counting it runs no query.
 */
class CountedChildCollection extends Collection
{
    private ?int $filteredStoreId = null;

    /** @var int[] Entity ids of the parents this collection is filtered to. */
    private array $parentIds = [];

    /**
     * @return $this
     */
    public function setProductFilter(mixed $product)
    {
        $this->parentIds[] = (int) $product->getId();

        return parent::setProductFilter($product);
    }

    /**
     * @return $this
     */
    public function addStoreFilter(mixed $store = null)
    {
        $this->filteredStoreId = match (true) {
            $store instanceof StoreInterface => (int) $store->getId(),
            is_numeric($store) => (int) $store,
            default => null,
        };

        return parent::addStoreFilter($store);
    }

    /**
     * The one parent this collection is filtered to, or null when it covers none or several.
     */
    public function getSoleParentId(): ?int
    {
        return count($this->parentIds) === 1 && $this->parentIds[0] > 0 ? $this->parentIds[0] : null;
    }

    /**
     * The store id the collection was filtered to, by id or by store, or null when left to the current store.
     */
    public function getFilteredStoreId(): ?int
    {
        return $this->filteredStoreId;
    }

    /**
     * Sets what getSize() answers, and nothing else: loading the items still queries as Magento does.
     */
    public function answerSizeWith(int $count): void
    {
        $this->_totalRecords = $count;
    }
}
