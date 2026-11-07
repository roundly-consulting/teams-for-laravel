<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Exceptions;

/**
 * A parent assignment the team hierarchy rejects.
 */
final class TeamHierarchyException extends TeamsException
{
    public static function nestingDisabled(): self
    {
        return new self((string) trans('teams::errors.nesting_disabled'));
    }

    /**
     * The new parent is the team itself or one of its descendants.
     */
    public static function cycle(int|string $team): self
    {
        return new self((string) trans('teams::errors.nesting_cycle', ['team' => (string) $team]));
    }

    public static function tooDeep(int $maxDepth): self
    {
        return new self(trans_choice('teams::errors.nesting_too_deep', $maxDepth));
    }
}
