<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Teams\Models\TeamRole;

/**
 * Resolves the Eloquent model backing a per-team role override from `teams.models.team_role`.
 *
 * The toolkit ModelResolver validates that the configured value is a real
 * Eloquent model; it cannot know it is *ours*, so anything that is not a
 * TeamRole (and so cannot answer the package's casts, scopes and relations)
 * falls back to the packaged model.
 */
final class TeamRoleModel
{
    /** @return class-string<TeamRole> */
    public static function class(): string
    {
        $model = ModelResolver::for('teams.models.team_role', TeamRole::class);

        return is_a($model, TeamRole::class, true) ? $model : TeamRole::class;
    }

    public static function new(): TeamRole
    {
        $model = self::class();

        return new $model;
    }

    /** @return Builder<TeamRole> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
