<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Scoping\TeamContext;
use RoundlyConsulting\Teams\Support\TeamModel;

/**
 * Check a restored team context again: the acting member it was set for must still exist
 * and still be allowed to act in every team in it — an active membership (expiry and, with
 * `teams.members.start_dates`, start date honoured) or with
 * `teams.nesting.inherit_roles_down` one on a team above. A team that is gone fails too.
 *
 * Read from the database at the moment it runs, so a membership removed or lapsed between
 * a job's dispatch and its start is caught. A context without an acting member (set by
 * the unchecked `Teams::setCurrent()`) passes: it was never a member's context.
 *
 * @internal Run by the queue listener when `teams.context.recheck_queued_jobs` is on.
 */
final readonly class RecheckTeamContextAction
{
    public function __construct(
        private TeamContext $context,
    ) {}

    /**
     * @throws TeamContextException when the acting member may no longer act in the context
     */
    public function execute(): void
    {
        $actor = $this->context->actor();
        $keys = $this->context->keys();

        if ($actor === null || $keys === null) {
            return;
        }

        $member = $actor->resolve();

        if ($member === null) {
            throw TeamContextException::actorMissing();
        }

        $teams = TeamModel::query()->whereKey($keys)->get()
            ->keyBy(static fn (Team $team): string => (string) $team->getKey());

        foreach ($keys as $key) {
            $team = $teams->get((string) $key);

            if (! $team instanceof Team || ! $team->isAccessibleBy($member)) {
                throw TeamContextException::revoked($key);
            }
        }
    }
}
