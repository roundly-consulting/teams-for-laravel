<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Teams\Models\JoinRequest;

/**
 * Resolves the Eloquent model backing a team join request from `teams.models.join_request`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class JoinRequestModel
{
    /** @return class-string<JoinRequest> */
    public static function class(): string
    {
        return ModelResolver::for('teams.models.join_request', JoinRequest::class);
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
