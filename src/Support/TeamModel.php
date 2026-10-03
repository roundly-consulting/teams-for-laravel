<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Teams\Models\Team;

/**
 * Resolves the Eloquent model backing a team from `teams.models.team`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class TeamModel
{
    /** @return class-string<Team> */
    public static function class(): string
    {
        return ModelResolver::for('teams.models.team', Team::class);
    }

    public static function new(): Team
    {
        $model = self::class();

        return new $model;
    }

    /** @return Builder<Team> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
