<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Events\TeamMemberRoleChanged;
use RoundlyConsulting\Teams\Exceptions\MemberNotFoundException;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;

final readonly class ChangeMemberRoleAction
{
    /**
     * Change a member's role, firing TeamMemberRoleChanged when it actually changes.
     *
     * @throws MemberNotFoundException when the model is not a member of the team
     */
    public function execute(Team $team, Model $member, string $role): Member
    {
        $membership = $team->findMember($member) ?? throw MemberNotFoundException::inTeam($team);

        $previousRole = $membership->role;

        if ($previousRole === $role) {
            return $membership;
        }

        $membership->update(['role' => $role]);

        TeamMemberRoleChanged::dispatch($membership, $previousRole);

        return $membership;
    }
}
