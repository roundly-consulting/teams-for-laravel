<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Support\TeamsConfig;
use RoundlyConsulting\Teams\TeamsManager;

final class MembersExpiringCommand extends Command
{
    /** @var string */
    protected $signature = 'teams:members:expiring
        {--days= : Window in days (defaults to teams.members.expiring_within)}
        {--notify : Fire MembershipExpiringSoon for each listed membership}';

    /** @var string */
    protected $description = 'Report memberships expiring within the configured window';

    public function handle(TeamsManager $teams): int
    {
        $days = $this->resolveDays();
        $notify = (bool) $this->option('notify');

        $members = $notify
            ? $teams->members()->notifyExpiring($days)
            : $teams->members()->expiring($days);

        if ($members->isEmpty()) {
            $this->info("No memberships are expiring within {$days} day(s).");

            return self::SUCCESS;
        }

        $rows = $members->map(static fn (Member $member): array => [
            (string) $member->team_id,
            $member->member_type.' #'.$member->member_id,
            $member->role ?? '—',
            (string) $member->expires_at?->toDateTimeString(),
            (string) $member->expires_at?->diffForHumans(),
        ])->all();

        $this->table(['Team', 'Member', 'Role', 'Expires at', 'In'], $rows);

        if ($notify) {
            $this->info("Dispatched MembershipExpiringSoon for {$members->count()} membership(s).");
        }

        return self::SUCCESS;
    }

    private function resolveDays(): int
    {
        $option = $this->option('days');

        if ($option !== null) {
            return (int) $option;
        }

        return TeamsConfig::membersExpiringWithin();
    }
}
