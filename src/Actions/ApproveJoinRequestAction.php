<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\DataTransferObjects\RespondToJoinRequestData;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestApproved;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;

final class ApproveJoinRequestAction
{
    public function __construct(
        private readonly AddMemberAction $addMember,
    ) {}

    /**
     * Approve a pending join request, adding the requester as a member. A
     * non-pending request is a no-op: the existing membership is returned to
     * guard against a double approval.
     */
    public function execute(JoinRequest $request, RespondToJoinRequestData $data): Member
    {
        /** @var Team $team */
        $team = $request->team;

        /** @var Model $requester */
        $requester = $request->requester;

        /** @var string $defaultRole */
        $defaultRole = config('teams.roles.default', 'member');

        $role = $data->role ?? $request->requested_role ?? $defaultRole;

        if (! $request->isPending()) {
            return $this->addMember->execute($team, new AddMemberData(
                member: $requester,
                role: $role,
            ));
        }

        $member = $this->addMember->execute($team, new AddMemberData(
            member: $requester,
            role: $role,
        ));

        $request->update([
            'status' => JoinRequestStatus::Approved,
            'responded_by_type' => $data->responder->getMorphClass(),
            'responded_by_id' => $data->responder->getKey(),
            'responded_at' => now(),
        ]);

        JoinRequestApproved::dispatch($request);

        return $member;
    }
}
