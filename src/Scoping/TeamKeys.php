<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Scoping;

use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Models\Team;

/**
 * Normalises "a team, a key, or a list of either" into a de-duplicated key list.
 *
 * @internal
 */
final class TeamKeys
{
    /**
     * @param  Team|int|string|iterable<Team|int|string>  $teams
     * @return list<int|string>
     */
    public static function from(Team|int|string|iterable $teams): array
    {
        $items = is_iterable($teams) ? $teams : [$teams];
        $keys = [];

        foreach ($items as $team) {
            $key = $team instanceof Team ? self::saved($team) : $team;
            $keys[(string) $key] = $key;
        }

        return array_values($keys);
    }

    private static function saved(Team $team): int|string
    {
        if (! $team->exists) {
            throw TeamContextException::unsavedTeam();
        }

        return TeamContext::keyOf($team);
    }
}
