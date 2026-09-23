<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model\Salability;

use Kingletas\CatalogBatch\Model\Config;
use Kingletas\CatalogBatch\Model\LinkField;
use Kingletas\CatalogBatch\Model\Salability\PendingSalableParents;
use Kingletas\CatalogBatch\Model\Salability\SalableChildCounter;
use Kingletas\CatalogBatch\Model\Salability\SalableChildCounts;
use Kingletas\CatalogBatch\Model\Salability\ServedSalableCounts;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

class SalableChildCounterTest extends TestCase
{
    private ServedSalableCounts $served;

    private PendingSalableParents $pending;

    /** @var list<array{0: int[], 1: int}> The parent link ids and store each count was asked for. */
    private array $asked = [];

    protected function setUp(): void
    {
        $this->served = new ServedSalableCounts();
        $this->pending = new PendingSalableParents();
    }

    /**
     * Noting a listing queries nothing; the first product asked about counts the whole listing.
     */
    public function testTheFirstQuestionCountsTheWholeListing(): void
    {
        $counter = $this->counter();
        $counter->remember([$this->product(7, 107), $this->product(8, 108)], 3);
        $this->assertSame([], $this->asked);

        $this->assertSame(2, $counter->countGroupOf(8, 3));
        $this->assertSame([[[107, 108], 3]], $this->asked);
        $this->assertSame(2, $this->served->forProduct(7, 3));
        $this->assertSame(0, $this->served->forProduct(8, 3));
    }

    public function testAProductNoListingNotedIsNotCounted(): void
    {
        $this->assertSame(0, $this->counter()->countGroupOf(7, 3));
        $this->assertSame([], $this->asked);
    }

    public function testTheSwitchBeingOffMeansNothingIsNoted(): void
    {
        $counter = $this->counter(false);
        $counter->remember([$this->product(7, 107)], 3);

        $this->assertSame(0, $counter->countGroupOf(7, 3));
        $this->assertSame([], $this->asked);
    }

    /**
     * A second listing on the same page that repeats a product already counted does not count it again.
     */
    public function testAProductAlreadyCountedInThisStoreIsNotCountedAgain(): void
    {
        $counter = $this->counter();
        $counter->remember([$this->product(7, 107)], 3);
        $counter->countGroupOf(7, 3);
        $counter->remember([$this->product(7, 107), $this->product(8, 108)], 3);
        $counter->countGroupOf(8, 3);

        $this->assertSame([[[107], 3], [[108], 3]], $this->asked);
    }

    public function testAListingLargerThanTheBatchSizeIsCountedInChunks(): void
    {
        $counter = $this->counter(true, 2);
        $counter->remember(array_map(fn (int $id): Product => $this->product($id, 100 + $id), [1, 2, 3]), 3);
        $counter->countGroupOf(1, 3);

        $this->assertSame([[[101, 102], 3], [[103], 3]], $this->asked);
    }

    private function counter(bool $enabled = true, int $batchSize = 100): SalableChildCounter
    {
        $config = $this->createMock(Config::class);
        $config->method('isSalabilityEnabled')->willReturn($enabled);
        $config->method('getBatchSize')->willReturn($batchSize);

        $counts = $this->createMock(SalableChildCounts::class);
        $counts->method('forParents')->willReturnCallback(function (array $linkIds, int $storeId): array {
            $this->asked[] = [$linkIds, $storeId];

            return array_combine($linkIds, array_map(static fn (int $id): int => $id === 107 ? 2 : 0, $linkIds));
        });

        $link = $this->createMock(LinkField::class);
        $link->method('product')->willReturn('row_id');

        return new SalableChildCounter($config, $counts, $this->served, $this->pending, $link);
    }

    private function product(int $entityId, int $linkId): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($entityId);
        $product->method('getTypeId')->willReturn('configurable');
        $product->method('getData')->willReturnCallback(
            static fn (string $key = ''): mixed => $key === 'row_id' ? $linkId : null
        );

        return $product;
    }
}
