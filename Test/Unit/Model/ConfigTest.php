<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model;

use Kingletas\CatalogBatch\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testTheSwitchIsReadPerStore(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path, string $scope, int $storeId): bool => $storeId === 2
        );

        $config = new Config($scopeConfig);

        $this->assertTrue($config->isEnabled(2));
        $this->assertFalse($config->isEnabled(1));
    }

    public function testAnUnsetOrUselessBatchSizeFallsBackToAWorkableOne(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame(100, (new Config($scopeConfig))->getBatchSize());
    }

    public function testAConfiguredBatchSizeIsUsed(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('25');

        $this->assertSame(25, (new Config($scopeConfig))->getBatchSize());
    }
}
