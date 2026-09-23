<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Wiring;

use Kingletas\CatalogBatch\Model\Config;
use Magento\Framework\Event\ObserverInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SimpleXMLElement;

/**
 * The XML in etc/ against the code it names, because a typo there fails at runtime and nowhere else.
 */
class WiringTest extends TestCase
{
    public function testEveryObserverNamedInTheEventsFileExistsAndIsOne(): void
    {
        foreach ($this->observers() as $class) {
            $this->assertTrue(class_exists($class), $class . ' is named in events.xml and does not exist');
            $this->assertTrue(
                (new ReflectionClass($class))->implementsInterface(ObserverInterface::class),
                $class . ' is wired as an observer and is not one'
            );
        }
    }

    public function testTheFrontendIsTheOnlyAreaThatObservesAnything(): void
    {
        $this->assertNotEmpty($this->observers());
        $this->assertSame([], glob($this->root() . '/etc/events.xml') ?: []);
    }

    /**
     * A switch with no default is a switch nobody can turn off again, so every path config.xml owns has one.
     */
    public function testEveryConfigPathTheCodeReadsHasADefault(): void
    {
        $defaults = simplexml_load_file($this->root() . '/etc/config.xml');
        $this->assertNotFalse($defaults);

        foreach (['enabled', 'salable_children', 'batch_size'] as $field) {
            $this->assertNotEmpty(
                $defaults->xpath('/config/default/kingletas_catalog_batch/general/' . $field) ?: [],
                $field . ' has no default in config.xml'
            );
        }
    }

    public function testTheAdminOffersEveryFieldTheDefaultsDeclare(): void
    {
        $system = simplexml_load_file($this->root() . '/etc/adminhtml/system.xml');
        $this->assertNotFalse($system);

        $ids = array_map(
            static fn (SimpleXMLElement $field): string => (string) $field['id'],
            $system->xpath('//section[@id="kingletas_catalog_batch"]//field') ?: []
        );

        $this->assertSame(['enabled', 'salable_children', 'batch_size'], $ids);
    }

    public function testBothSwitchesAreOffOnInstall(): void
    {
        $defaults = simplexml_load_file($this->root() . '/etc/config.xml');
        $this->assertNotFalse($defaults);

        foreach (['enabled', 'salable_children'] as $field) {
            $value = $defaults->xpath('/config/default/kingletas_catalog_batch/general/' . $field) ?: [];
            $this->assertSame('0', (string) reset($value), $field . ' is not off on install');
        }
    }

    /**
     * The salable count reaches Magento through two constructor arguments of the type model, never a plugin.
     */
    public function testTheSalableCountIsWiredAsArgumentsOfTheTypeModel(): void
    {
        $config = simplexml_load_file($this->root() . '/etc/frontend/di.xml');
        $this->assertNotFalse($config);
        $type = 'Magento\\ConfigurableProduct\\Model\\Product\\Type\\Configurable';
        $arguments = [];

        foreach ($config->xpath('//type[@name="' . $type . '"]/arguments/argument') ?: [] as $argument) {
            $arguments[(string) $argument['name']] = trim((string) $argument);
        }

        $this->assertSame(
            'Kingletas\\CatalogBatch\\Model\\Salability\\CountingSalableProcessor',
            $arguments['salableProcessor'] ?? null
        );
        $factory = $arguments['productCollectionFactory'] ?? '';
        $virtual = $config->xpath('//virtualType[@name="' . $factory . '"]/arguments/argument[@name="instanceName"]');
        $this->assertSame(
            'Kingletas\\CatalogBatch\\Model\\Salability\\CountedChildCollection',
            trim((string) ($virtual[0] ?? ''))
        );
        $this->assertTrue(class_exists(trim((string) ($virtual[0] ?? ''))));
        $this->assertSame([], $config->xpath('//plugin[contains(@type, "Salab")]') ?: []);
    }

    public function testTheConfigClassReadsTheSectionTheXmlDeclares(): void
    {
        $source = file_get_contents((new ReflectionClass(Config::class))->getFileName() ?: '');

        $this->assertIsString($source);
        $this->assertStringContainsString("SECTION = 'kingletas_catalog_batch'", $source);
    }

    /**
     * The status command is the only thing wired outside the frontend, and its Proxy is generated from a real class.
     */
    public function testEveryClassTheGlobalDiFileNamesExists(): void
    {
        $config = simplexml_load_file($this->root() . '/etc/di.xml');
        $this->assertNotFalse($config);

        $types = $config->xpath('//type') ?: [];
        $objects = $config->xpath('//*[@xsi:type="object"]') ?: [];
        $names = array_merge(
            array_map(static fn (SimpleXMLElement $type): string => (string) $type['name'], $types),
            array_map(static fn (SimpleXMLElement $item): string => trim((string) $item), $objects)
        );

        $this->assertNotEmpty($names);

        foreach ($names as $class) {
            $this->assertTrue(
                class_exists($class) || interface_exists($class),
                $class . ' is named in di.xml and does not exist'
            );
        }
    }

    /**
     * @return string[]
     */
    private function observers(): array
    {
        $events = simplexml_load_file($this->root() . '/etc/frontend/events.xml');
        $this->assertNotFalse($events);

        return array_map(
            static fn (SimpleXMLElement $observer): string => (string) $observer['instance'],
            $events->xpath('//observer') ?: []
        );
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
