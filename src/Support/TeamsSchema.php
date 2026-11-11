<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignIdColumnDefinition;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/**
 * Schema helpers for the package's migrations: the teams table's own key and the columns
 * pointing at it, both typed by `teams.primary_key_type`. On `bigint` they emit exactly what
 * the migrations emitted before the setting existed (`id()`, `foreignId()`).
 *
 * Published migrations call it, so it stays put; it is not a host-facing API — scoped host
 * tables use the `$table->teamScope()` macro.
 *
 * @internal
 */
final class TeamsSchema
{
    public static function keyType(): KeyType
    {
        return KeyType::fromConfig('teams.primary_key_type');
    }

    /**
     * The teams table's `id`. uuid/ulid keys get an explicit primary() command: a fluent
     * `->primary()` is added after every other command, which PostgreSQL refuses once another
     * table's foreign key points here in the same batch.
     */
    public static function primaryKey(Blueprint $table): void
    {
        $type = self::keyType();

        match ($type) {
            KeyType::BigInt => $table->id(),
            KeyType::Uuid => $table->uuid('id'),
            KeyType::Ulid => $table->ulid('id'),
        };

        if ($type !== KeyType::BigInt) {
            $table->primary('id');
        }
    }

    /**
     * A column pointing at a team, typed like `teams.id`. The caller adds the constraint.
     */
    public static function teamForeignKey(Blueprint $table, string $column): ForeignIdColumnDefinition
    {
        return match (self::keyType()) {
            KeyType::BigInt => $table->foreignId($column),
            KeyType::Uuid => $table->foreignUuid($column),
            KeyType::Ulid => $table->foreignUlid($column),
        };
    }
}
