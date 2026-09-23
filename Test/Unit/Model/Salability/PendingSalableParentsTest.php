<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model\Salability;

use Kingletas\CatalogBatch\Model\Salability\PendingSalableParents;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

class PendingSalableParentsTest extends TestCase
{
    public function testTheFirstProductAskedAboutBringsItsWholeListing(): void
    {
        $pending = new PendingSalableParents();
        $pending->remember([$this->product(7), $this->product(8), $this->product(9, 'simple')], 3);

        $this->assertSame([7, 8], array_keys($pending->takeGroupOf(8, 3)));
    }

    /**
     * A group is handed over once, so the second product in it is never counted twice.
     */
    public function testAGroupIsTakenOnlyOnce(): void
    {
        $pending = new PendingSalableParents();
        $pending->remember([$this->product(7), $this->product(8)], 3);
        $pending->takeGroupOf(7, 3);

        $this->assertSame([], $pending->takeGroupOf(8, 3));
    }

    public function testAnotherStoreDoesNotSeeTheGroup(): void
    {
        $pending = new PendingSalableParents();
        $pending->remember([$this->product(7)], 3);

        $this->assertSame([], $pending->takeGroupOf(7, 4));
        $this->assertSame([7], array_keys($pending->takeGroupOf(7, 3)));
    }

    /**
     * A product in two collections belongs to the later one, and taking it leaves the earlier group whole.
     */
    public function testAProductInTwoListingsBelongsToTheLaterOne(): void
    {
        $pending = new PendingSalableParents();
        $pending->remember([$this->product(7), $this->product(8)], 3);
        $pending->remember([$this->product(8), $this->product(10)], 3);

        $this->assertSame([8, 10], array_keys($pending->takeGroupOf(8, 3)));
        $this->assertSame([7, 8], array_keys($pending->takeGroupOf(7, 3)));
    }

    public function testAListingWithNoConfigurableIsNotKept(): void
    {
        $pending = new PendingSalableParents();
        $pending->remember([$this->product(9, 'simple')], 3);

        $this->assertSame([], $pending->takeGroupOf(9, 3));
    }

    private function product(int $id, string $type = 'configurable'): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getTypeId')->willReturn($type);

        return $product;
    }
}
