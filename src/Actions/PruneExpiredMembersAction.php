<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Support\MemberModel;

final readonly class PruneExpiredMembersAction
{
    /**
     * Force-delete members whose expiry passed more than the configured
     * "prune_after" interval ago — the same rows and the same path as
     * `php artisan model:prune` (soft-deleted rows included), so
     * MembershipExpired fires per pruned row either way.
     */
    public function execute(): int
    {
        $count = 0;

        MemberModel::new()->prunable()
            ->withTrashed()
            ->each(function (Member $member) use (&$count): void {
                $member->prune();
                $count++;
            });

        return $count;
    }
}
