<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model\Salability;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * The configurables each loaded collection held, kept together so the first one Magento counts brings in the rest.
 */
class PendingSalableParents
{
    /** @var array<int, array{products: array<int, Product>, storeId: int}> */
    private array $groups = [];

    /** @var array<int, array<int, int>> Store id to entity id to the group it was last remembered in. */
    private array $groupOf = [];

    private int $next = 0;

    /**
     * @param Product[] $products
     */
    public function remember(array $products, int $storeId): void
    {
        $pending = [];

        foreach ($products as $product) {
            $id = (int) $product->getId();

            if ($id > 0 && $product->getTypeId() === Configurable::TYPE_CODE) {
                $pending[$id] = $product;
            }
        }

        if ($pending === []) {
            return;
        }

        $group = $this->next++;
        $this->groups[$group] = ['products' => $pending, 'storeId' => $storeId];

        foreach (array_keys($pending) as $id) {
            $this->groupOf[$storeId][$id] = $group;
        }
    }

    /**
     * Removes and returns the products remembered with this one for this store, so each group is counted once.
     *
     * @return array<int, Product> Entity id to product; empty when the product was not remembered.
     */
    public function takeGroupOf(int $entityId, int $storeId): array
    {
        $group = $this->groupOf[$storeId][$entityId] ?? null;

        if ($group === null || !isset($this->groups[$group])) {
            return [];
        }

        $products = $this->groups[$group]['products'];
        unset($this->groups[$group]);

        foreach (array_keys($products) as $id) {
            if (($this->groupOf[$storeId][$id] ?? null) === $group) {
                unset($this->groupOf[$storeId][$id]);
            }
        }

        return $products;
    }
}
