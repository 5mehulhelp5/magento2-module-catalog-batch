<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model\Status;

use Kingletas\CatalogBatch\Model\Status\AnswerHistory;
use Kingletas\CatalogBatch\Model\Status\AnswerTally;
use PHPUnit\Framework\TestCase;

class AnswerTallyTest extends TestCase
{
    /** @var array<int, int[]> Each call to the history, as answered and pre-empted. */
    private array $added = [];

    public function testAFlushHandsOverWhatTheRequestCounted(): void
    {
        $tally = new AnswerTally($this->history());

        $tally->answered(12);
        $tally->answered(3);
        $tally->preempted();
        $tally->flush();

        $this->assertSame([[15, 1]], $this->added);
    }

    public function testAFlushStartsTheNextRequestFromNothing(): void
    {
        $tally = new AnswerTally($this->history());

        $tally->preempted();
        $tally->flush();
        $tally->flush();

        $this->assertSame([[0, 1], [0, 0]], $this->added);
    }

    private function history(): AnswerHistory
    {
        $history = $this->createMock(AnswerHistory::class);
        $history->method('add')->willReturnCallback(function (int $answered, int $preempted): void {
            $this->added[] = [$answered, $preempted];
        });

        return $history;
    }
}
