<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Unit\Model;

use Kingletas\CatalogBatch\Model\OptionRows;
use Kingletas\CatalogBatch\Test\Support\LinkFieldDouble;
use Kingletas\CatalogBatch\Test\Support\StubbedDatabase;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\Source\SourceInterface;
use PHPUnit\Framework\TestCase;

class OptionRowsTest extends TestCase
{
    use LinkFieldDouble;
    use StubbedDatabase;

    public function testOneQueryCoversEveryParentAndEveryAttribute(): void
    {
        $this->answers['catalog_product_super_attribute'] = [
            $this->row(5, 93, '52', 'Blue'),
            $this->row(5, 94, '167', 'Small'),
            $this->row(6, 93, '53', 'Red'),
        ];

        $rows = $this->optionRows()->forParents([5, 6], [5 => [93, 94], 6 => [93]], 3, 1);

        $this->assertCount(1, $this->queriesOn('catalog_product_super_attribute'));
        $this->assertSame('Blue', $rows[5][93][0]['option_title']);
        $this->assertSame('Small', $rows[5][94][0]['option_title']);
        $this->assertSame('Red', $rows[6][93][0]['option_title']);
        $this->assertArrayNotHasKey('parent_link_id', $rows[5][93][0]);
    }

    public function testNothingToAskAboutMeansNoQuery(): void
    {
        $this->assertSame([], $this->optionRows()->forParents([], [], 1, 1));
        $this->assertSame([], $this->optionRows()->forParents([5], [], 1, 1));
        $this->assertSame([], $this->queries);
    }

    /**
     * Magento's own storefront plugin filters these rows to the website, so a batched query has to as well.
     */
    public function testTheWebsiteReachesTheQuery(): void
    {
        $this->answers['catalog_product_super_attribute'] = [$this->row(5, 93, '52', 'Blue')];

        $this->optionRows()->forParents([5], [5 => [93]], 3, 4);

        $joins = [];

        foreach ($this->queriesOn('catalog_product_super_attribute')[0]['calls'] as [$method, $args]) {
            if ($method === 'joinInner') {
                $joins[] = (string) ($args[1] ?? '');
            }
        }

        $this->assertNotEmpty(array_filter(
            $joins,
            static fn (string $join): bool => str_contains($join, 'website_id = 4')
        ));
    }

    /**
     * An attribute with a source model takes its titles from the source, which is what Magento's own provider does.
     */
    public function testASourceModelOverwritesTheTitlesTheQueryReturned(): void
    {
        $this->answers['catalog_product_super_attribute'] = [$this->row(5, 93, '52', 'From the table')];

        $rows = $this->optionRows('Some\Source\Model')->forParents([5], [5 => [93]], 3, 1);

        $this->assertSame('From the source', $rows[5][93][0]['option_title']);
        $this->assertSame('From the source', $rows[5][93][0]['default_title']);
    }

    public function testAValueTheSourceDoesNotKnowLosesItsTitle(): void
    {
        $this->answers['catalog_product_super_attribute'] = [$this->row(5, 93, '999', 'From the table')];

        $rows = $this->optionRows('Some\Source\Model')->forParents([5], [5 => [93]], 3, 1);

        $this->assertFalse($rows[5][93][0]['option_title']);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $parent, int $attributeId, string $valueIndex, string $title): array
    {
        return [
            'parent_link_id' => (string) $parent,
            'attribute_id' => (string) $attributeId,
            'sku' => 'child-' . $valueIndex,
            'product_id' => '100',
            'attribute_code' => 'color',
            'value_index' => $valueIndex,
            'super_attribute_label' => 'Color',
            'option_title' => $title,
            'default_title' => $title,
        ];
    }

    private function optionRows(?string $sourceModel = null): OptionRows
    {
        $source = $this->createMock(SourceInterface::class);
        $source->method('getAllOptions')->willReturn([
            ['value' => '', 'label' => ' '],
            ['value' => '52', 'label' => 'From the source'],
        ]);

        $attribute = $this->createMock(EavAttribute::class);
        $attribute->method('getBackendTable')->willReturn('catalog_product_entity_int');
        $attribute->method('getSourceModel')->willReturn($sourceModel);
        $attribute->method('getSource')->willReturn($source);

        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->willReturn($attribute);

        return new OptionRows($this->resourceConnection(), $this->linkField(), $eavConfig);
    }
}
