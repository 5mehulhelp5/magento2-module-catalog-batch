<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Console\Command;

use Kingletas\CatalogBatch\Console\Command\StatusCommand;
use Kingletas\CatalogBatch\Model\Status\AnswerHistory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class StatusCommandTest extends TestCase
{
    /** @var int[] The windows the command asked the history for. */
    private array $windows = [];

    public function testItSaysHowManyProductsAnotherModuleAnsweredFirst(): void
    {
        $tester = new CommandTester($this->command(['answered' => 40, 'preempted' => 7]));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertMatchesRegularExpression('/answered by this module\s*\|\s*40/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/answered first by another module\s*\|\s*7/', $tester->getDisplay());
        $this->assertSame([24], $this->windows);
    }

    public function testTheWindowCanBeNarrowedButNeverBelowAnHour(): void
    {
        $tester = new CommandTester($this->command(['answered' => 0, 'preempted' => 0]));

        $tester->execute(['--hours' => '0']);

        $this->assertSame([1], $this->windows);
    }

    /**
     * @param array{answered: int, preempted: int} $totals
     */
    private function command(array $totals): StatusCommand
    {
        $history = $this->createMock(AnswerHistory::class);
        $history->method('totals')->willReturnCallback(function (int $hours) use ($totals): array {
            $this->windows[] = $hours;

            return $totals;
        });

        return new StatusCommand($history, 'kingletas:catalog-batch:status');
    }
}
