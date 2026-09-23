<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Wiring;

use PHPUnit\Framework\TestCase;

/**
 * The catalog index module answers the same Magento call, so the order the two plugins run in is declared.
 */
class ConfigurableAttributesOrderTest extends TestCase
{
    /** The order both modules' READMEs document: the catalog index module first, then this one. */
    private const array EXPECTED = ['index' => 10, 'batch' => 20];

    private const string INDEX_PLUGIN = 'kingletas_catalog_index_seed_configurable_attributes';
    private const string BATCH_PLUGIN = 'kingletas_catalog_batch_seed_attributes_on_demand';

    public function testThisModuleDeclaresTheDocumentedSortOrder(): void
    {
        $this->assertSame(
            self::EXPECTED['batch'],
            $this->sortOrder($this->moduleDir() . '/etc/frontend/di.xml', self::BATCH_PLUGIN)
        );
    }

    /**
     * Read from the index module's own di.xml when it is checked out beside this one, else from its documented value.
     */
    public function testThisModuleAnswersAfterTheIndexModule(): void
    {
        $indexFile = dirname($this->moduleDir()) . '/module-catalog-index/etc/frontend/di.xml';
        $index = is_file($indexFile) ? $this->sortOrder($indexFile, self::INDEX_PLUGIN) : self::EXPECTED['index'];

        $this->assertNotNull($index, 'The catalog index plugin declares no sortOrder.');
        $this->assertGreaterThan(
            $index,
            (int) $this->sortOrder($this->moduleDir() . '/etc/frontend/di.xml', self::BATCH_PLUGIN)
        );
    }

    private function sortOrder(string $file, string $plugin): ?int
    {
        $config = simplexml_load_file($file);
        $this->assertNotFalse($config, $file . ' does not parse');

        $found = $config->xpath(sprintf('//plugin[@name="%s"]/@sortOrder', $plugin)) ?: [];

        return $found === [] ? null : (int) $found[0];
    }

    private function moduleDir(): string
    {
        return dirname(__DIR__, 2);
    }
}
