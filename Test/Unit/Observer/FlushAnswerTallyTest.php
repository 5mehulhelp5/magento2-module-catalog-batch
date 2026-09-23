<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Observer;

use Kingletas\CatalogBatch\Model\Status\AnswerTally;
use Kingletas\CatalogBatch\Observer\FlushAnswerTally;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class FlushAnswerTallyTest extends TestCase
{
    public function testTheTallyIsFlushedOncePerResponse(): void
    {
        $tally = $this->createMock(AnswerTally::class);
        $tally->expects($this->once())->method('flush');

        (new FlushAnswerTally($tally, $this->createMock(LoggerInterface::class)))->execute(new Observer());
    }

    /**
     * Counting is a diagnostic, so a cache that refuses the write is logged and the response still goes out.
     */
    public function testACacheFailureIsLoggedNotThrown(): void
    {
        $tally = $this->createMock(AnswerTally::class);
        $tally->method('flush')->willThrowException(new RuntimeException('cache down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        (new FlushAnswerTally($tally, $logger))->execute(new Observer());
    }
}
