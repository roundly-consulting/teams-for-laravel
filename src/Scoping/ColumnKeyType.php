<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Scoping;

use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/**
 * Whether a column, as the schema builder's `getColumns()` describes it, can hold a key of a
 * given type: an integer for bigint, a native `uuid` or 36-character string for uuid, a
 * 26-character string for ulid.
 *
 * SQLite keeps no length on `varchar`, so there a uuid and a ulid column look alike and
 * either passes for both; an integer column still never passes for a string key.
 *
 * @internal
 */
final class ColumnKeyType
{
    /**
     * @param  array<string, mixed>  $column
     */
    public static function holds(KeyType $type, array $column): bool
    {
        $typeName = self::typeName($column);

        if (str_contains($typeName, 'int')) {
            return $type === KeyType::BigInt;
        }

        if ($typeName === 'uuid') {
            return $type === KeyType::Uuid;
        }

        if ($type === KeyType::BigInt || ! self::isString($typeName)) {
            return false;
        }

        $length = self::length($column);

        return $length === null || $length === match ($type) {
            KeyType::Uuid => 36,
            KeyType::Ulid => 26,
        };
    }

    /**
     * The full database type, as shown to a human (`bigint`, `character(26)`, `uuid`).
     *
     * @param  array<string, mixed>  $column
     */
    public static function describe(array $column): string
    {
        $type = $column['type'] ?? null;

        return is_string($type) && $type !== '' ? $type : self::typeName($column);
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private static function typeName(array $column): string
    {
        $name = $column['type_name'] ?? '';

        return strtolower(is_string($name) ? $name : '');
    }

    private static function isString(string $typeName): bool
    {
        return in_array($typeName, ['char', 'bpchar', 'character', 'varchar', 'character varying', 'nchar', 'nvarchar', 'text', 'binary', 'varbinary'], true);
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private static function length(array $column): ?int
    {
        $type = $column['type'] ?? '';

        return is_string($type) && preg_match('/\((\d+)\)/', $type, $match) === 1 ? (int) $match[1] : null;
    }
}
