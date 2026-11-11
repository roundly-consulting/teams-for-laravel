<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Scoping;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Teams\Support\TeamModel;

/**
 * The key type of a column that points AT a team (`team_id` on a scoped table, a team's
 * `parent_id`), read from the configured team model: the key it really writes.
 *
 * The packaged model keys itself by `teams.primary_key_type`, so this is that setting. A
 * `teams.models.team` subclass using `HasUuids`/`HasUlids` itself (the way to key teams by
 * UUID/ULID before the setting existed) wins, so `teamScope()` keeps matching its ids;
 * `teams:doctor` fails when such a subclass and the setting disagree.
 *
 * Deliberately not `teams.key_type`: that setting types the polymorphic columns pointing
 * at HOST models (owner, member, inviter).
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
            default => TeamModel::new()->configuredKeyType(),
        };
    }
}
