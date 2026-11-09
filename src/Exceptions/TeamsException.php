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

    /**
     * A membership start date while `teams.members.start_dates` is off: honouring it is
     * impossible, and ignoring it would make the member active at once.
     */
    public static function startDatesDisabled(): self
    {
        return new self((string) trans('teams::errors.start_dates_disabled'));
    }

    /**
     * A membership whose start is not before its expiry would never be active.
     */
    public static function invalidMembershipWindow(): self
    {
        return new self((string) trans('teams::errors.membership_window'));
    }

    /**
     * A `Team::scopedKey()` suffix with a `.` or `..` segment (or a NUL byte) — it could
     * climb out of the team's own prefix.
     */
    public static function unsafeScopedKey(string $suffix): self
    {
        return new self((string) trans('teams::errors.unsafe_scoped_key', ['suffix' => $suffix]));
    }
}
