<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * The column that joins a product to its attribute rows, which is row_id where content staging is installed.
 */
class LinkField
{
    public function __construct(
        private readonly MetadataPool $metadataPool
    ) {
    }

    public function product(): string
    {
        return $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
    }
}
