<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Observer;

use Kingletas\CatalogBatch\Model\PendingConfigurables;
use Kingletas\CatalogBatch\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Notes the configurables a just-loaded collection holds, so they can be answered together if the page asks about one.
 */
class SeedListingConfigurables implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly PendingConfigurables $pending,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $collection = $observer->getEvent()->getData('collection');

        if (!$collection instanceof Collection) {
            return;
        }

        $store = $this->storeManager->getStore();

        if (!$this->config->isEnabled((int) $store->getId())) {
            return;
        }

        $this->pending->remember($this->products($collection), (int) $store->getId(), (int) $store->getWebsiteId());
    }

    /**
     * A collection is declared to hold data objects, and only a product has a type and a link field.
     *
     * @return Product[]
     */
    private function products(Collection $collection): array
    {
        return array_values(array_filter(
            $collection->getItems(),
            static fn (DataObject $item): bool => $item instanceof Product
        ));
    }
}
