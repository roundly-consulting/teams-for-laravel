<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\Events\TeamMemberRoleChanged;
use RoundlyConsulting\Teams\Models\Member;

final class ChangeMemberRoleAction
{
    public function execute(Member $member, string $role): Member
    {
        $previousRole = $member->role;

        if ($previousRole === $role) {
            return $member;
        }

        $member->update(['role' => $role]);

        TeamMemberRoleChanged::dispatch($member, $previousRole);

        return $member;
    }
}
