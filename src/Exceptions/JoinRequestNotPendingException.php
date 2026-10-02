<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Exceptions;

use RoundlyConsulting\Teams\Models\JoinRequest;

/**
 * Thrown when a join request is approved or denied after it was already resolved
 * (approved, denied or expired) — including by a concurrent responder. A resolved
 * request is final: re-approving one must never re-add or re-role its requester.
 */
final class JoinRequestNotPendingException extends TeamsException
{
    public static function for(JoinRequest $request): self
    {
        return new self(trans('teams::errors.join_request_not_pending', ['id' => (string) $request->getKey()]));
    }
}
