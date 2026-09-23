<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model\Salability;

use Kingletas\CatalogBatch\Model\Config;
use Kingletas\CatalogBatch\Model\LinkField;
use Magento\Catalog\Model\Product;

/**
 * Notes a listing's configurables, and counts a whole listing's buyable children the first time Magento asks for one.
 */
class SalableChildCounter
{
    public function __construct(
        private readonly Config $config,
        private readonly SalableChildCounts $counts,
        private readonly ServedSalableCounts $served,
        private readonly PendingSalableParents $pending,
        private readonly LinkField $linkField
    ) {
    }

    /**
     * Nothing is queried here; a collection whose products are never counted costs nothing.
     *
     * @param Product[] $products
     */
    public function remember(array $products, int $storeId): void
    {
        if ($this->config->isSalabilityEnabled($storeId)) {
            $this->pending->remember($products, $storeId);
        }
    }

    /**
     * Counts the group this product was loaded with, and says how many products were answered for.
     */
    public function countGroupOf(int $entityId, int $storeId): int
    {
        $answered = 0;
        $parents = $this->parents($this->pending->takeGroupOf($entityId, $storeId), $storeId);

        foreach (array_chunk($parents, $this->config->getBatchSize(), true) as $chunk) {
            foreach ($this->counts->forParents(array_keys($chunk), $storeId) as $linkId => $count) {
                $this->served->remember((int) $chunk[$linkId]->getId(), $storeId, $count);
                $answered++;
            }
        }

        return $answered;
    }

    /**
     * Configurables not yet counted for this store, keyed by the link id their children are filed under.
     *
     * @param Product[] $products
     * @return array<int, Product>
     */
    private function parents(array $products, int $storeId): array
    {
        $link = $this->linkField->product();
        $parents = [];

        foreach ($products as $product) {
            $linkId = (int) $product->getData($link);

            if ($linkId > 0 && $this->served->forProduct((int) $product->getId(), $storeId) === null) {
                $parents[$linkId] = $product;
            }
        }

        return $parents;
    }
}
