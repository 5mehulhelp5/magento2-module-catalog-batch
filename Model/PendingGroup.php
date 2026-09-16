<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model;

use Magento\Catalog\Model\Product;

/**
 * The configurables one collection loaded, with the store and website they were loaded for.
 */
class PendingGroup
{
    /**
     * @param array<int, Product> $products Entity id to product.
     */
    public function __construct(
        public readonly array $products,
        public readonly int $storeId,
        public readonly int $websiteId
    ) {
    }
}
