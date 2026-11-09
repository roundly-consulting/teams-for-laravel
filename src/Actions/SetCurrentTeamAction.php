<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\Events\TeamContextSet;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Scoping\TeamContext;

/**
 * Make `$team` the current team of this request, job or command — or clear the context
 * with null.
 *
 * UNCHECKED: no membership check at all. It is for system code (a webhook, a command, a
 * seeder) acting for a team, never for a team id a user supplied — use
 * {@see SwitchTeamAction} (`Teams::switchTo()`) for that. The context records no acting
 * member, so `teams.context.recheck_queued_jobs` has nobody to re-check for it.
 */
final readonly class SetCurrentTeamAction
{
    public function __construct(
        private TeamContext $context,
    ) {}

    public function execute(?Team $team): void
    {
        if ($team === null) {
            $this->context->clear();

            TeamContextSet::dispatch(new Collection);

            return;
        }

        if (! $team->exists) {
            throw TeamContextException::unsavedTeam();
        }

        $this->context->set(new Collection([$team]), multiple: false);

        TeamContextSet::dispatch(new Collection([$team]));
    }
}
