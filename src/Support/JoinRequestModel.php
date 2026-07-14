<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Teams\Models\JoinRequest;

/**
 * Resolves the Eloquent model backing a team join request from `teams.models.join_request`.
 *
 * The toolkit ModelResolver validates that the configured value is a real
 * Eloquent model; it cannot know it is *ours*, so anything that is not a
 * JoinRequest (and so cannot answer the package's casts, scopes and relations)
 * falls back to the packaged model.
 */
final class JoinRequestModel
{
    /** @return class-string<JoinRequest> */
    public static function class(): string
    {
        $model = ModelResolver::for('teams.models.join_request', JoinRequest::class);

        return is_a($model, JoinRequest::class, true) ? $model : JoinRequest::class;
    }

    public static function new(): JoinRequest
    {
        $model = self::class();

        return new $model;
    }

    /** @return Builder<JoinRequest> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
