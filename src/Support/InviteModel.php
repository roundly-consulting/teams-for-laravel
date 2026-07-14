<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Teams\Models\Invite;

/**
 * Resolves the Eloquent model backing a team invite from `teams.models.invite`.
 *
 * The toolkit ModelResolver validates that the configured value is a real
 * Eloquent model; it cannot know it is *ours*, so anything that is not a
 * Invite (and so cannot answer the package's casts, scopes and relations)
 * falls back to the packaged model.
 */
final class InviteModel
{
    /** @return class-string<Invite> */
    public static function class(): string
    {
        $model = ModelResolver::for('teams.models.invite', Invite::class);

        return is_a($model, Invite::class, true) ? $model : Invite::class;
    }

    public static function new(): Invite
    {
        $model = self::class();

        return new $model;
    }

    /** @return Builder<Invite> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
