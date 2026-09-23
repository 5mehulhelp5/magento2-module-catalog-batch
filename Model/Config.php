<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * What this module is allowed to batch, per store view.
 */
class Config
{
    private const string SECTION = 'kingletas_catalog_batch';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(int $storeId): bool
    {
        return $this->isSetFlag('general/enabled', $storeId);
    }

    /**
     * Whether a listing's salable-child counts are worked out for the page at once, which has its own switch.
     */
    public function isSalabilityEnabled(int $storeId): bool
    {
        return $this->isSetFlag('general/salable_children', $storeId);
    }

    /**
     * A page with more configurables than this is batched in chunks, so one query never grows without a bound.
     */
    public function getBatchSize(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::SECTION . '/general/batch_size');

        return $value > 0 ? $value : 100;
    }

    private function isSetFlag(string $path, int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::SECTION . '/' . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
