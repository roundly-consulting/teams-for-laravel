<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Exceptions;

use RoundlyConsulting\Teams\Models\Team;

/**
 * A team-scoped read or write that the current team context does not allow.
 *
 * Thrown rather than returned: a scoped model never quietly reads or writes another
 * team's rows, and never falls back to every team's rows when no context is set.
 */
final class TeamContextException extends TeamsException
{
    /**
     * No team context is set — a write always needs one, and a read when
     * `teams.scoping.without_context` is `throw`.
     */
    public static function missing(): self
    {
        return new self((string) trans('teams::errors.context_missing'));
    }

    /**
     * Several teams are in context and the write (or check) did not name one.
     */
    public static function ambiguous(): self
    {
        return new self((string) trans('teams::errors.context_ambiguous'));
    }

    /**
     * A write for a team the current context does not include.
     */
    public static function outsideContext(int|string $team): self
    {
        return new self((string) trans('teams::errors.context_outside', ['team' => (string) $team]));
    }

    /**
     * Multi-team mode was asked for a team the user holds no active membership of.
     */
    public static function notAccessible(Team $team): self
    {
        return new self((string) trans('teams::errors.context_not_accessible', ['team' => (string) $team->getKey()]));
    }

    /**
     * Multi-team mode needs a user to check memberships against.
     */
    public static function noUser(): self
    {
        return new self((string) trans('teams::errors.context_no_user'));
    }

    /**
     * A team that was never saved has no key to scope by.
     */
    public static function unsavedTeam(): self
    {
        return new self((string) trans('teams::errors.context_unsaved_team'));
    }

    /**
     * The context middleware sits on a route without the team parameter it reads.
     */
    public static function missingRouteParameter(string $parameter): self
    {
        return new self((string) trans('teams::errors.context_route_parameter', ['parameter' => $parameter]));
    }
}
