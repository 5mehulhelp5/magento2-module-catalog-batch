<?php
/**
 * @package   Kingletas_CatalogBatch
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogBatch\Test\Support;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use SplObjectStorage;

/**
 * A database that answers by table name and records every query built against it.
 */
trait StubbedDatabase
{
    /** @var array<int, array{table: string, calls: array<int, array{0: string, 1: mixed[]}>}> */
    protected array $queries = [];

    /** @var array<string, mixed> Table name to the rows, column or pairs a fetch returns. */
    protected array $answers = [];

    /** @var array<int, array{0: string, 1: mixed[]}> */
    protected array $writes = [];

    /** @var SplObjectStorage<Select, int>|null */
    protected ?SplObjectStorage $selectIds = null;

    protected bool $tablesExist = true;

    protected function resourceConnection(): ResourceConnection
    {
        $this->selectIds ??= new SplObjectStorage();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturnCallback(fn (): Select => $this->recordingSelect());

        foreach (['fetchAll', 'fetchCol', 'fetchPairs', 'fetchOne', 'fetchRow'] as $method) {
            $connection->method($method)->willReturnCallback(
                fn (mixed $select): mixed => $this->answerFor($method, $select)
            );
        }

        foreach (['insertOnDuplicate', 'delete', 'insertMultiple'] as $method) {
            $connection->method($method)->willReturnCallback(function (mixed ...$args) use ($method): int {
                $this->writes[] = [$method, $args];

                return 1;
            });
        }

        $connection->method('isTableExists')->willReturnCallback(fn (): bool => $this->tablesExist);
        $connection->method('tableColumnExists')->willReturnCallback(fn (): bool => $this->tablesExist);
        $connection->method('quoteInto')->willReturnCallback(
            static fn (string $text, mixed $value): string => str_replace('?', "'" . $value . "'", $text)
        );
        $connection->method('quoteIdentifier')->willReturnCallback(
            static fn (string $name): string => '`' . $name . '`'
        );
        $connection->method('getCheckSql')->willReturnCallback(
            static fn (string $test, string $yes, string $no): string => "IF({$test}, {$yes}, {$no})"
        );
        $connection->method('getIfNullSql')->willReturnCallback(
            static fn (string $value, mixed $fallback = 0): string => "IFNULL({$value}, {$fallback})"
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnCallback(static fn (string $table): string => $table);

        return $resource;
    }

    /**
     * @return array<int, array{table: string, calls: array<int, array{0: string, 1: mixed[]}>}>
     */
    protected function queriesOn(string $table): array
    {
        return array_values(array_filter($this->queries, static fn (array $query): bool => $query['table'] === $table));
    }

    /**
     * @return mixed[] Every value passed to where() with this condition, across all queries.
     */
    protected function whereValues(string $condition): array
    {
        $values = [];

        foreach ($this->queries as $query) {
            foreach ($query['calls'] as [$method, $args]) {
                if ($method === 'where' && ($args[0] ?? null) === $condition) {
                    $values[] = $args[1] ?? null;
                }
            }
        }

        return $values;
    }

    protected function recordingSelect(): Select
    {
        $select = $this->createMock(Select::class);
        $id = count($this->queries);
        $this->queries[$id] = ['table' => '', 'calls' => []];
        $this->selectIds[$select] = $id;

        $fluent = [
            'from',
            'where',
            'join',
            'joinInner',
            'joinLeft',
            'joinRight',
            'columns',
            'order',
            'limit',
            'group',
            'having',
            'distinct',
            'union',
        ];

        foreach ($fluent as $method) {
            $select->method($method)->willReturnCallback(function (mixed ...$args) use ($select, $id, $method): Select {
                if ($method === 'from' && $this->queries[$id]['table'] === '') {
                    $this->queries[$id]['table'] = $this->tableName($args[0] ?? '');
                }

                $this->queries[$id]['calls'][] = [$method, $args];

                return $select;
            });
        }

        $select->method('assemble')->willReturn('select#' . $id);

        return $select;
    }

    protected function answerFor(string $method, mixed $select): mixed
    {
        $id = $select instanceof Select && $this->selectIds->contains($select)
            ? $this->selectIds[$select]
            : (int) substr((string) $select, 7);
        $table = $this->queries[$id]['table'] ?? '';
        $answer = $this->answers[$table] ?? null;

        if (is_callable($answer)) {
            return $answer($this->queries[$id]);
        }

        return $answer ?? match ($method) {
            'fetchOne' => false,
            'fetchRow' => false,
            default => [],
        };
    }

    protected function tableName(mixed $from): string
    {
        if (is_array($from)) {
            return (string) reset($from);
        }

        return (string) $from;
    }
}
