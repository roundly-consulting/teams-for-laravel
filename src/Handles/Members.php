<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Handles;

use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\Actions\DispatchExpiringMembershipsAction;
use RoundlyConsulting\Teams\Actions\PruneExpiredMembersAction;
use RoundlyConsulting\Teams\Enums\TeamOperation;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * `Teams::members()` — memberships across teams: expiry reporting and pruning.
 */
final readonly class Members
{
    public function __construct(
        private TeamsManager $manager,
    ) {}

    /**
     * Active memberships lapsing within `$days` (default `teams.members.expiring_within`).
     * Read-only; see {@see self::notifyExpiring()} to fire the events.
     *
     * @return Collection<int, Member>
     */
    public function expiring(?int $days = null): Collection
    {
        return app(DispatchExpiringMembershipsAction::class)->execute($days, notify: false);
    }

    /**
     * Fire `MembershipExpiringSoon` for every active membership lapsing within
     * `$days` (default `teams.members.expiring_within`).
     *
     * @return Collection<int, Member> the memberships notified
     */
    public function notifyExpiring(?int $days = null): Collection
    {
        return $this->manager->perform(
            TeamOperation::NotifyExpiringMembers,
            DispatchExpiringMembershipsAction::class,
            static fn (DispatchExpiringMembershipsAction $action): Collection => $action->execute($days, notify: true),
            ['days' => $days],
        );
    }

    /**
     * Force-delete memberships whose expiry passed more than `teams.members.prune_after`
     * ago, firing `MembershipExpired` for each.
     *
     * @return int the number of memberships pruned
     */
    public function prune(): int
    {
        return $this->manager->perform(
            TeamOperation::PruneMembers,
            PruneExpiredMembersAction::class,
            static fn (PruneExpiredMembersAction $action): int => $action->execute(),
        );
    }
}
