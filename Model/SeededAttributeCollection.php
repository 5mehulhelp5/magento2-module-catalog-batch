<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Attribute\Collection;

/**
 * A configurable attribute collection whose items came from a page-wide query, so iterating it loads nothing.
 */
class SeededAttributeCollection extends Collection
{
    private int $seededStoreId = 0;

    /**
     * Magento asks the collection whether it is loaded before querying, so saying yes is what skips the query.
     *
     * @param Attribute[] $attributes
     */
    public function seed(array $attributes, int $storeId): void
    {
        $this->seededStoreId = $storeId;

        foreach ($attributes as $attribute) {
            $this->addItem($attribute);
        }

        $this->_setIsLoaded(true);
    }

    /**
     * The parent reads this off a product it was filtered by, and a seeded collection was filtered by nothing.
     */
    public function getStoreId(): int
    {
        return $this->seededStoreId;
    }
}
