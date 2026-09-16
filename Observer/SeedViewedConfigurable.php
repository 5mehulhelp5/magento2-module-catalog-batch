<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Observer;

use Kingletas\CatalogBatch\Model\AttributeCollectionSeeder;
use Kingletas\CatalogBatch\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * A product page has one configurable, so this trades its three queries for two rather than for none.
 */
class SeedViewedConfigurable implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly AttributeCollectionSeeder $seeder,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getData('product');

        if (!$product instanceof Product) {
            return;
        }

        $store = $this->storeManager->getStore();

        if (!$this->config->isEnabled((int) $store->getId())) {
            return;
        }

        $this->seeder->seed([$product], (int) $store->getId(), (int) $store->getWebsiteId());
    }
}
