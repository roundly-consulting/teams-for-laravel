<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\Events\TeamMemberDeleted;
use RoundlyConsulting\Teams\Models\Team;

final readonly class RemoveMemberAction
{
    public function execute(Team $team, Model $member): bool
    {
        $found = $team->findMember($member);

        if ($found === null) {
            return false;
        }

        $deleted = (bool) $found->delete();

        if ($deleted) {
            TeamMemberDeleted::dispatch($found);
        }

        return $deleted;
    }
}
