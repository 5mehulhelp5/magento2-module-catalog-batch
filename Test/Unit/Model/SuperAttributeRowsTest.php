<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model;

use Kingletas\CatalogBatch\Model\SuperAttributeRows;
use Kingletas\CatalogBatch\Test\Support\StubbedDatabase;
use PHPUnit\Framework\TestCase;

class SuperAttributeRowsTest extends TestCase
{
    use StubbedDatabase;

    public function testEveryParentInThePageIsCoveredByOneQuery(): void
    {
        $this->answers['catalog_product_super_attribute'] = [
            [
                'product_id' => '5',
                'attribute_id' => '93',
                'position' => '0',
                'product_super_attribute_id' => '11',
                'label' => 'Colour',
                'use_default' => '0',
            ],
            [
                'product_id' => '6',
                'attribute_id' => '94',
                'position' => '1',
                'product_super_attribute_id' => '12',
                'label' => null,
                'use_default' => null,
            ],
        ];

        $rows = (new SuperAttributeRows($this->resourceConnection()))->forParents([5, 6], 3);

        $this->assertCount(1, $this->queriesOn('catalog_product_super_attribute'));
        $this->assertSame([5, 6], array_keys($rows));
        $this->assertSame('Colour', $rows[5][11]['label']);
        $this->assertSame(0, $rows[5][11]['use_default']);
        $this->assertNull($rows[6][12]['label']);
        $this->assertNull($rows[6][12]['use_default']);
    }

    public function testNoParentsMeansNoQuery(): void
    {
        $this->assertSame([], (new SuperAttributeRows($this->resourceConnection()))->forParents([], 1));
        $this->assertSame([], $this->queries);
    }

    /**
     * The store's own label wins over the default one, and the query is what decides that rather than the caller.
     */
    public function testTheStoreScopeReachesTheLabelJoin(): void
    {
        (new SuperAttributeRows($this->resourceConnection()))->forParents([5], 7);

        $joins = [];

        foreach ($this->queriesOn('catalog_product_super_attribute')[0]['calls'] as [$method, $args]) {
            if ($method === 'joinLeft') {
                $joins[] = (string) ($args[1] ?? '');
            }
        }

        $this->assertCount(2, $joins);
        $this->assertStringContainsString('store_id = 0', $joins[0]);
        $this->assertStringContainsString('store_id = 7', $joins[1]);
    }
}
