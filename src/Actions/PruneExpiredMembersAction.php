<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\Events\MembershipExpired;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Support\MemberModel;

final readonly class PruneExpiredMembersAction
{
    /**
     * Force-delete members whose expiry passed more than the configured
     * "prune_after" interval ago, firing MembershipExpired per pruned row.
     */
    public function execute(): int
    {
        /** @var string $after */
        $after = config('teams.members.prune_after', '30 days');

        $threshold = now()->sub($after);

        $count = 0;

        MemberModel::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $threshold)
            ->each(function (Member $member) use (&$count): void {
                MembershipExpired::dispatch($member);
                $member->forceDelete();
                $count++;
            });

        return $count;
    }
}
