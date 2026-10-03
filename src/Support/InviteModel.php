<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Teams\Models\Invite;

/**
 * Resolves the Eloquent model backing a team invite from `teams.models.invite`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class InviteModel
{
    /** @return class-string<Invite> */
    public static function class(): string
    {
        return ModelResolver::for('teams.models.invite', Invite::class);
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
