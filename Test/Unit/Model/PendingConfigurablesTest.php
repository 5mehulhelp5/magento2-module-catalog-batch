<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model;

use Kingletas\CatalogBatch\Model\PendingConfigurables;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

class PendingConfigurablesTest extends TestCase
{
    public function testAskingAboutOneProductHandsOverItsWholeCollection(): void
    {
        $pending = new PendingConfigurables();
        $pending->remember([$this->product(5), $this->product(6)], 1, 2);

        $group = $pending->takeGroupOf(6);

        $this->assertNotNull($group);
        $this->assertSame([5, 6], array_keys($group->products));
        $this->assertSame([1, 2], [$group->storeId, $group->websiteId]);
    }

    /**
     * A group is answered once, so asking about its other products afterwards finds nothing left to do.
     */
    public function testAGroupIsHandedOverOnlyOnce(): void
    {
        $pending = new PendingConfigurables();
        $pending->remember([$this->product(5), $this->product(6)], 1, 1);
        $pending->takeGroupOf(5);

        $this->assertNull($pending->takeGroupOf(6));
    }

    /**
     * A related products block loads its own collection, and it should not be answered with the page's listing.
     */
    public function testEachCollectionIsItsOwnGroup(): void
    {
        $pending = new PendingConfigurables();
        $pending->remember([$this->product(5)], 1, 1);
        $pending->remember([$this->product(8)], 1, 1);

        $this->assertSame([5], array_keys($pending->takeGroupOf(5)->products ?? []));
        $this->assertSame([8], array_keys($pending->takeGroupOf(8)->products ?? []));
    }

    public function testSimpleProductsAndAlreadyAnsweredConfigurablesAreNotRemembered(): void
    {
        $pending = new PendingConfigurables();
        $pending->remember([$this->product(5, 'simple'), $this->product(6, 'configurable', true)], 1, 1);

        $this->assertNull($pending->takeGroupOf(5));
        $this->assertNull($pending->takeGroupOf(6));
    }

    public function testAProductNobodyRememberedHasNoGroup(): void
    {
        $this->assertNull((new PendingConfigurables())->takeGroupOf(5));
    }

    private function product(int $id, string $type = 'configurable', bool $answered = false): Product
    {
        $product = $this->createMock(Product::class);
        $data = new DataObject($answered ? ['_cache_instance_configurable_attributes' => true] : []);
        $product->method('getId')->willReturn($id);
        $product->method('getTypeId')->willReturn($type);
        $product->method('hasData')->willReturnCallback(fn (string $key): bool => $data->hasData($key));

        return $product;
    }
}
