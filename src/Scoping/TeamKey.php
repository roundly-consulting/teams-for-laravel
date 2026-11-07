<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Scoping;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Teams\Support\TeamModel;

/**
 * The key type of a column that points AT a team (`team_id` on a scoped table, a team's
 * `parent_id`), read from the configured team model.
 *
 * Deliberately not `teams.key_type`: that setting types the polymorphic columns pointing
 * at HOST models (owner, member, inviter), and the shipped `teams` table keeps an
 * auto-incrementing id whatever it says. A ULID-keyed user base sets `key_type` = ulid;
 * typing `team_id` from it would build a ULID column against a bigint `teams.id` and the
 * foreign key would fail. A host that really keys its teams by UUID/ULID swaps in a
 * `teams.models.team` subclass using `HasUuids`/`HasUlids`, and this follows it.
 *
 * @internal
 */
final class TeamKey
{
    public static function type(): KeyType
    {
        $traits = class_uses_recursive(TeamModel::class());

        return match (true) {
            in_array(HasUlids::class, $traits, true) => KeyType::Ulid,
            in_array(HasUuids::class, $traits, true) => KeyType::Uuid,
            default => KeyType::BigInt,
        };
    }
}
