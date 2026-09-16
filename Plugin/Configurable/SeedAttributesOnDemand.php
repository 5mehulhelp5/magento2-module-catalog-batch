<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Plugin\Configurable;

use Kingletas\CatalogBatch\Model\AttributeCollectionSeeder;
use Kingletas\CatalogBatch\Model\PendingConfigurables;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * Answers every configurable a collection loaded the first time Magento asks one of them for its attributes.
 */
class SeedAttributesOnDemand
{
    public function __construct(
        private readonly PendingConfigurables $pending,
        private readonly AttributeCollectionSeeder $seeder
    ) {
    }

    /**
     * A collection whose products are never asked, such as a related products block, costs nothing.
     *
     * @return mixed[]|null Always null, which tells Magento to keep its own arguments.
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function beforeGetConfigurableAttributes(Configurable $subject, Product $product): ?array
    {
        $id = (int) $product->getId();

        if ($id === 0 || $product->hasData(AttributeCollectionSeeder::CONFIGURABLE_ATTRIBUTES)) {
            return null;
        }

        $group = $this->pending->takeGroupOf($id);

        if ($group === null) {
            return null;
        }

        $this->seeder->seed(array_values($group->products), $group->storeId, $group->websiteId);
        $this->copyFromTwin($product, $group->products[$id] ?? null);

        return null;
    }

    /**
     * Magento can ask with another object for the same product, which should get the answer its twin was given.
     */
    private function copyFromTwin(Product $product, ?Product $twin): void
    {
        if ($twin === null
            || $twin === $product
            || !$twin->hasData(AttributeCollectionSeeder::CONFIGURABLE_ATTRIBUTES)
        ) {
            return;
        }

        $product->setData(
            AttributeCollectionSeeder::CONFIGURABLE_ATTRIBUTES,
            $twin->getData(AttributeCollectionSeeder::CONFIGURABLE_ATTRIBUTES)
        );
    }
}
