<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Scoping;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignKeyDefinition;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Teams\Support\TeamModel;

/**
 * The body of the `$table->teamScope()` Blueprint macro: the team column of a scoped
 * table, typed like the team key ({@see TeamKey}), indexed (Postgres creates no index for
 * a foreign key on its own) and constrained onto the configured team model's table.
 *
 * Returns the foreign key definition, so a host chooses the delete behaviour itself:
 * `$table->teamScope()->cascadeOnDelete()`. The default restricts — force-deleting a team
 * that still owns rows fails instead of silently taking them along.
 *
 * @internal
 */
final class TeamScopeColumn
{
    public static function add(Blueprint $table, string $column = 'team_id', bool $nullable = false): ForeignKeyDefinition
    {
        $definition = match (TeamKey::type()) {
            KeyType::BigInt => $table->foreignId($column),
            KeyType::Uuid => $table->foreignUuid($column),
            KeyType::Ulid => $table->foreignUlid($column),
        };

        if ($nullable) {
            $definition->nullable();
        }

        $definition->index();

        $team = TeamModel::new();

        return $definition->constrained($team->getTable(), $team->getKeyName());
    }
}
