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

        foreach (['enabled', 'batch_size'] as $field) {
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

        $this->assertSame(['enabled', 'batch_size'], $ids);
    }

    public function testTheSwitchIsOffOnInstall(): void
    {
        $defaults = simplexml_load_file($this->root() . '/etc/config.xml');
        $this->assertNotFalse($defaults);
        $enabled = $defaults->xpath('/config/default/kingletas_catalog_batch/general/enabled') ?: [];

        $this->assertSame('0', (string) reset($enabled));
    }

    public function testTheConfigClassReadsTheSectionTheXmlDeclares(): void
    {
        $source = file_get_contents((new ReflectionClass(Config::class))->getFileName() ?: '');

        $this->assertIsString($source);
        $this->assertStringContainsString("SECTION = 'kingletas_catalog_batch'", $source);
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
