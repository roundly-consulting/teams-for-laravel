<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\Events\TeamMoved;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Support\TeamHierarchy;
use RoundlyConsulting\Teams\Support\TeamModel;

/**
 * Place a team under `$parent`, or detach it to a root with null. Needs
 * `teams.nesting.enabled`; a cycle or a subtree pushed past `teams.nesting.max_depth`
 * throws TeamHierarchyException and changes nothing.
 *
 * Both rows are locked for the transaction, so two concurrent moves that would each be
 * fine alone (A under B, B under A) cannot both commit into a cycle.
 */
final readonly class MoveTeamAction
{
    public function execute(Team $team, ?Team $parent): Team
    {
        return $team->getConnection()->transaction(function () use ($team, $parent): Team {
            TeamModel::query()
                ->withTrashed()
                ->whereKey(array_values(array_filter([$team->getKey(), $parent?->getKey()], static fn (mixed $key): bool => $key !== null)))
                ->lockForUpdate()
                ->get();

            $previous = $team->getRawOriginal(TeamHierarchy::PARENT_COLUMN);

            if ($parent === null) {
                $team->parent()->dissociate();
            } else {
                $team->parent()->associate($parent);
            }

            $team->save();

            TeamMoved::dispatch($team, $parent, is_int($previous) || is_string($previous) ? $previous : null);

            return $team;
        });
    }
}
