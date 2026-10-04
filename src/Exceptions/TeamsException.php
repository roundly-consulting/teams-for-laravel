<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Exceptions;

use RoundlyConsulting\Teams\Models\Team;
use RuntimeException;

class TeamsException extends RuntimeException
{
    public static function joinPolicyForbidsRequests(): self
    {
        return new self((string) trans('teams::errors.join_policy_forbids_requests'));
    }

    /**
     * A join request from a model that already holds an active membership. Joining
     * is for newcomers: a request must never be a path to a different role.
     */
    public static function alreadyMember(Team $team): self
    {
        return new self(trans('teams::errors.already_member', ['team' => (string) $team->getKey()]));
    }

    public static function maxSeatsReached(int $maxSeats): self
    {
        return new self(trans_choice('teams::errors.max_seats_reached', $maxSeats));
    }
}
