<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Observer;

use Kingletas\CatalogBatch\Model\Status\AnswerTally;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Writes the request's answer counts once, when the response is ready to send.
 */
class FlushAnswerTally implements ObserverInterface
{
    public function __construct(
        private readonly AnswerTally $tally,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function execute(Observer $observer): void
    {
        try {
            $this->tally->flush();
        } catch (Throwable $e) {
            $this->logger->warning('Catalog batch: answer counts could not be saved.', ['exception' => $e]);
        }
    }
}
