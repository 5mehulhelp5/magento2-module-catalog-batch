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

/**
 * The configurables this request loaded and nobody has asked about yet, grouped by the collection that loaded them.
 */
class PendingConfigurables
{
    /** @var array<int, PendingGroup> */
    private array $groups = [];

    /** @var array<int, int> Entity id to the group it was last remembered in. */
    private array $groupOf = [];

    private int $next = 0;

    /**
     * @param Product[] $products
     */
    public function remember(array $products, int $storeId, int $websiteId): void
    {
        $pending = [];

        foreach ($products as $product) {
            $id = (int) $product->getId();

            if ($id > 0
                && $product->getTypeId() === Configurable::TYPE_CODE
                && !$product->hasData(AttributeCollectionSeeder::CONFIGURABLE_ATTRIBUTES)
            ) {
                $pending[$id] = $product;
            }
        }

        if ($pending === []) {
            return;
        }

        $group = $this->next++;
        $this->groups[$group] = new PendingGroup($pending, $storeId, $websiteId);

        foreach (array_keys($pending) as $id) {
            $this->groupOf[$id] = $group;
        }
    }

    /**
     * Removes and returns the group a product was remembered in, so each group is answered at most once.
     */
    public function takeGroupOf(int $entityId): ?PendingGroup
    {
        $group = $this->groupOf[$entityId] ?? null;

        if ($group === null || !isset($this->groups[$group])) {
            return null;
        }

        $pending = $this->groups[$group];
        unset($this->groups[$group]);

        foreach (array_keys($pending->products) as $id) {
            if (($this->groupOf[$id] ?? null) === $group) {
                unset($this->groupOf[$id]);
            }
        }

        return $pending;
    }
}
