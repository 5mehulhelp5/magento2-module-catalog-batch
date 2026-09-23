<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model\Status;

use Kingletas\CatalogBatch\Model\Status\AnswerHistory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;

class AnswerHistoryTest extends TestCase
{
    private const int NOON = 1789646400;

    /** @var array<string, string> What the cache holds, by key. */
    private array $entries = [];

    private int $now = self::NOON;

    public function testCountsAddUpAcrossRequestsAndHours(): void
    {
        $history = $this->history();

        $history->add(10, 2);
        $history->add(5, 0);
        $this->now += 3600;
        $history->add(1, 1);

        $this->assertSame(['answered' => 16, 'preempted' => 3], $history->totals(2));
        $this->assertSame(['answered' => 1, 'preempted' => 1], $history->totals(1));
    }

    public function testAnHourOutsideTheWindowIsNotCounted(): void
    {
        $history = $this->history();

        $history->add(10, 2);
        $this->now += 2 * 3600;

        $this->assertSame(['answered' => 0, 'preempted' => 0], $history->totals(2));
    }

    /**
     * A request that counted nothing writes nothing, so a store with the switch off costs the cache nothing.
     */
    public function testAnEmptyAddWritesNothing(): void
    {
        $this->history()->add(0, 0);

        $this->assertSame([], $this->entries);
    }

    public function testAnUnreadableEntryCountsAsNothing(): void
    {
        $history = $this->history();
        $this->entries['kingletas_catalog_batch_answers_' . gmdate('YmdH', self::NOON)] = '"not a bucket"';

        $this->assertSame(['answered' => 0, 'preempted' => 0], $history->totals(1));
    }

    private function history(): AnswerHistory
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn (string $key): string|false => $this->entries[$key] ?? false);
        $cache->method('save')->willReturnCallback(function (string $data, string $key): bool {
            $this->entries[$key] = $data;

            return true;
        });
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturnCallback(fn (): int => $this->now);

        return new AnswerHistory($cache, new Json(), $dateTime);
    }
}
