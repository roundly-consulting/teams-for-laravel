<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Teams\Models\TeamRole;

/**
 * Resolves the Eloquent model backing a per-team role override from `teams.models.team_role`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class TeamRoleModel
{
    /** @return class-string<TeamRole> */
    public static function class(): string
    {
        return ModelResolver::for('teams.models.team_role', TeamRole::class);
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
