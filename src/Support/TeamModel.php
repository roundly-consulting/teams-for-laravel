<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Teams\Models\Team;

/**
 * Resolves the Eloquent model backing a team from `teams.models.team`.
 *
 * The toolkit ModelResolver validates that the configured value is a real
 * Eloquent model; it cannot know it is *ours*, so anything that is not a
 * Team (and so cannot answer the package's casts, scopes and relations)
 * falls back to the packaged model.
 */
final class TeamModel
{
    /** @return class-string<Team> */
    public static function class(): string
    {
        $model = ModelResolver::for('teams.models.team', Team::class);

        return is_a($model, Team::class, true) ? $model : Team::class;
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
