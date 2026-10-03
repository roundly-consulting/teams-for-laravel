<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Teams\Models\Member;

/**
 * Resolves the Eloquent model backing a team membership from `teams.models.member`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class MemberModel
{
    /** @return class-string<Member> */
    public static function class(): string
    {
        return ModelResolver::for('teams.models.member', Member::class);
    }

    public static function new(): Member
    {
        $model = self::class();

        return new $model;
    }

    /** @return Builder<Member> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
