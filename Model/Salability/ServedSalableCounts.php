<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model\Salability;

/**
 * The salable-child counts this request has already worked out, per store, so Magento never has to count again.
 */
class ServedSalableCounts
{
    /** @var array<int, array<int, int>> Store id to entity id to count. */
    private array $counts = [];

    /**
     * Keyed by entity id, because under content staging a row id can equal another product's entity id.
     */
    public function remember(int $entityId, int $storeId, int $count): void
    {
        if ($entityId > 0) {
            $this->counts[$storeId][$entityId] = $count;
        }
    }

    /**
     * Null when nothing was counted for this product in this store, which is not the same as none being buyable.
     */
    public function forProduct(int $entityId, int $storeId): ?int
    {
        return $this->counts[$storeId][$entityId] ?? null;
    }
}
