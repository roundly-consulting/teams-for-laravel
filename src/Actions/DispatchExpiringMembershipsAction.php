<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\Events\MembershipExpiringSoon;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Support\MemberModel;
use RoundlyConsulting\Teams\Support\TeamsConfig;

final readonly class DispatchExpiringMembershipsAction
{
    /**
     * Collect active memberships lapsing within $withinDays (default
     * teams.members.expiring_within). When $notify is true, fire
     * MembershipExpiringSoon for each. Already-expired and never-expiring
     * memberships are excluded.
     *
     * @return Collection<int, Member>
     */
    public function execute(?int $withinDays = null, bool $notify = true): Collection
    {
        $withinDays ??= TeamsConfig::membersExpiringWithin();

        /** @var Collection<int, Member> $members */
        $members = MemberModel::query()->expiringWithin($withinDays)->get();

        if ($notify) {
            $members->each(static fn (Member $member) => MembershipExpiringSoon::dispatch($member));
        }

        return $members;
    }
}
