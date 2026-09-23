<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Model\Status;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Hourly answer counts kept in the application cache, approximate under concurrency and never written to the database.
 */
class AnswerHistory
{
    private const string PREFIX = 'kingletas_catalog_batch_answers_';
    private const int LIFETIME = 172800;
    private const int HOUR = 3600;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * A request that counted nothing writes nothing, so a store with the switch off costs the cache nothing.
     */
    public function add(int $answered, int $preempted): void
    {
        if ($answered === 0 && $preempted === 0) {
            return;
        }

        $key = $this->key((int) $this->dateTime->gmtTimestamp());
        $bucket = $this->load($key);
        $bucket['answered'] += $answered;
        $bucket['preempted'] += $preempted;

        $this->cache->save((string) $this->json->serialize($bucket), $key, [], self::LIFETIME);
    }

    /**
     * @return array{answered: int, preempted: int} Totals over the last hours.
     */
    public function totals(int $hours): array
    {
        $now = (int) $this->dateTime->gmtTimestamp();
        $totals = ['answered' => 0, 'preempted' => 0];
        $hours = max(1, $hours);

        for ($offset = 0; $offset < $hours; $offset++) {
            $bucket = $this->load($this->key($now - $offset * self::HOUR));
            $totals['answered'] += $bucket['answered'];
            $totals['preempted'] += $bucket['preempted'];
        }

        return $totals;
    }

    private function key(int $timestamp): string
    {
        return self::PREFIX . gmdate('YmdH', $timestamp);
    }

    /**
     * @return array{answered: int, preempted: int}
     */
    private function load(string $key): array
    {
        $raw = $this->cache->load($key);
        $decoded = is_string($raw) && $raw !== '' ? $this->json->unserialize($raw) : [];
        $decoded = is_array($decoded) ? $decoded : [];

        return [
            'answered' => (int) ($decoded['answered'] ?? 0),
            'preempted' => (int) ($decoded['preempted'] ?? 0),
        ];
    }
}
