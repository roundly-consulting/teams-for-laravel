<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\Events\TeamMemberAdded;
use RoundlyConsulting\Teams\Events\TeamMemberRoleChanged;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;

final class AddMemberAction
{
    /**
     * Add a member to a team. The operation is idempotent: adding an existing
     * member does not throw — the existing membership is returned, with its role
     * updated when the requested role differs.
     */
    public function execute(Team $team, AddMemberData $data): Member
    {
        $existing = $team->findMember($data->member);

        if ($existing !== null) {
            if ($existing->role !== $data->role) {
                $previousRole = $existing->role;
                $existing->update(['role' => $data->role]);

                TeamMemberRoleChanged::dispatch($existing, $previousRole);
            }

            return $existing;
        }

        /** @var Member $created */
        $created = $team->members()->create([
            'member_type' => $data->member->getMorphClass(),
            'member_id' => $data->member->getKey(),
            'role' => $data->role,
            'meta' => new Collection($data->meta),
        ]);

        TeamMemberAdded::dispatch($created);

        return $created;
    }
}
