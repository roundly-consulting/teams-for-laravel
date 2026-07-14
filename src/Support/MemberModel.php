<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Teams\Models\Member;

/**
 * Resolves the Eloquent model backing a team membership from `teams.models.member`.
 *
 * The toolkit ModelResolver validates that the configured value is a real
 * Eloquent model; it cannot know it is *ours*, so anything that is not a
 * Member (and so cannot answer the package's casts, scopes and relations)
 * falls back to the packaged model.
 */
final class MemberModel
{
    /** @return class-string<Member> */
    public static function class(): string
    {
        $model = ModelResolver::for('teams.models.member', Member::class);

        return is_a($model, Member::class, true) ? $model : Member::class;
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
