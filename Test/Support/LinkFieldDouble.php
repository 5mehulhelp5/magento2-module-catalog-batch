<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Support;

use Kingletas\CatalogBatch\Model\LinkField;

/**
 * A link field that reports the column a store without content staging uses, or any column a test names.
 */
trait LinkFieldDouble
{
    protected function linkField(string $field = 'entity_id'): LinkField
    {
        $link = $this->createMock(LinkField::class);
        $link->method('product')->willReturn($field);

        return $link;
    }
}
