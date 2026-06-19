<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\Events\MembershipExpiringSoon;
use RoundlyConsulting\Teams\Models\Member;

final class DispatchExpiringMembershipsAction
{
    /**
     * Collect active memberships lapsing within $withinDays. When $notify is
     * true, fire MembershipExpiringSoon for each. Already-expired and
     * never-expiring memberships are excluded.
     *
     * @return Collection<int, Member>
     */
    public function execute(int $withinDays, bool $notify = true): Collection
    {
        /** @var class-string<Member> $model */
        $model = config('teams.models.member', Member::class);

        /** @var Collection<int, Member> $members */
        $members = $model::query()->expiringWithin($withinDays)->get();

        if ($notify) {
            $members->each(static fn (Member $member) => MembershipExpiringSoon::dispatch($member));
        }

        return $members;
    }
}
