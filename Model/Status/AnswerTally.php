<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model\Status;

/**
 * Counts, per request, the products this module answered and the ones another plugin answered before it could.
 */
class AnswerTally
{
    private int $answered = 0;

    private int $preempted = 0;

    public function __construct(
        private readonly AnswerHistory $history
    ) {
    }

    public function answered(int $count): void
    {
        $this->answered += $count;
    }

    public function preempted(): void
    {
        $this->preempted++;
    }

    public function flush(): void
    {
        $this->history->add($this->answered, $this->preempted);
        $this->answered = 0;
        $this->preempted = 0;
    }
}
