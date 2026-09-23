<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model\Salability;

use Kingletas\CatalogBatch\Model\Salability\ServedSalableCounts;
use PHPUnit\Framework\TestCase;

class ServedSalableCountsTest extends TestCase
{
    public function testACountIsServedForTheStoreItWasCountedIn(): void
    {
        $served = new ServedSalableCounts();
        $served->remember(7, 1, 3);

        $this->assertSame(3, $served->forProduct(7, 1));
        $this->assertNull($served->forProduct(7, 2));
    }

    /**
     * No buyable child is an answer, and has to be told apart from never having been counted.
     */
    public function testZeroIsAnAnswerAndNothingCountedIsNull(): void
    {
        $served = new ServedSalableCounts();
        $served->remember(7, 1, 0);

        $this->assertSame(0, $served->forProduct(7, 1));
        $this->assertNull($served->forProduct(8, 1));
    }

    public function testAProductWithNoIdIsNotRemembered(): void
    {
        $served = new ServedSalableCounts();
        $served->remember(0, 1, 2);

        $this->assertNull($served->forProduct(0, 1));
    }
}
