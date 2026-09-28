<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Exceptions;

use RoundlyConsulting\Teams\Models\JoinRequest;

/**
 * Thrown when a team-scoped handle is handed a join request of another team —
 * scoping is a security boundary, so it is refused rather than acted on.
 */
final class JoinRequestNotFoundException extends TeamsException
{
    public static function inTeam(JoinRequest $request): self
    {
        return new self(trans('teams::errors.join_request_not_in_team', ['id' => (string) $request->getKey()]));
    }
}
