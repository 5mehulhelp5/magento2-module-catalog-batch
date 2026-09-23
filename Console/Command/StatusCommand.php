<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Console\Command;

use Kingletas\CatalogBatch\Model\Status\AnswerHistory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shows how many configurable products this module answered, and how many another module answered before it could.
 */
class StatusCommand extends Command
{
    private const string OPTION_HOURS = 'hours';

    public function __construct(
        private readonly AnswerHistory $history,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setDescription(
            (string) __('Show how many configurables this module answered, and how many another module answered first.')
        )
            ->addOption(
                self::OPTION_HOURS,
                null,
                InputOption::VALUE_REQUIRED,
                (string) __('Hours of counts to total.'),
                '24'
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hours = max(1, (int) $input->getOption(self::OPTION_HOURS));
        $totals = $this->history->totals($hours);

        $table = new Table($output);
        $table->setHeaders([(string) __('products'), (string) __('last %1 hours', $hours)]);
        $table->addRow([(string) __('answered by this module'), (string) $totals['answered']]);
        $table->addRow([(string) __('answered first by another module'), (string) $totals['preempted']]);
        $table->render();

        return Command::SUCCESS;
    }
}
