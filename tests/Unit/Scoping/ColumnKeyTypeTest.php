<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Teams\Scoping\ColumnKeyType;

/**
 * The column shapes each engine reports through `getColumns()` for a bigint, uuid and ulid key.
 */
it('tells which key types a column can hold', function (array $column, array $holds): void {
    foreach (KeyType::cases() as $type) {
        expect(ColumnKeyType::holds($type, $column))->toBe(in_array($type, $holds, true), "{$column['type']} / {$type->value}");
    }
})->with([
    'pgsql bigint' => [['type_name' => 'int8', 'type' => 'bigint'], [KeyType::BigInt]],
    'pgsql uuid' => [['type_name' => 'uuid', 'type' => 'uuid'], [KeyType::Uuid]],
    'pgsql ulid' => [['type_name' => 'bpchar', 'type' => 'character(26)'], [KeyType::Ulid]],
    'mysql bigint' => [['type_name' => 'bigint', 'type' => 'bigint unsigned'], [KeyType::BigInt]],
    'mysql uuid' => [['type_name' => 'char', 'type' => 'char(36)'], [KeyType::Uuid]],
    'mysql ulid' => [['type_name' => 'char', 'type' => 'char(26)'], [KeyType::Ulid]],
    'sqlite integer' => [['type_name' => 'integer', 'type' => 'integer'], [KeyType::BigInt]],
    // SQLite keeps no length: a uuid and a ulid column look alike.
    'sqlite varchar' => [['type_name' => 'varchar', 'type' => 'varchar'], [KeyType::Uuid, KeyType::Ulid]],
    'a string of another length' => [['type_name' => 'varchar', 'type' => 'varchar(255)'], []],
    'not a key column at all' => [['type_name' => 'json', 'type' => 'json'], []],
]);

it('describes a column by its full type, falling back to the type name', function (): void {
    expect(ColumnKeyType::describe(['type_name' => 'bpchar', 'type' => 'character(26)']))->toBe('character(26)')
        ->and(ColumnKeyType::describe(['type_name' => 'INT8']))->toBe('int8')
        ->and(ColumnKeyType::describe([]))->toBe('');
});
