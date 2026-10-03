<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\DataTransferObjects\RespondToJoinRequestData;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestApproved;
use RoundlyConsulting\Teams\Exceptions\JoinRequestNotPendingException;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Options\DefaultMemberRole;
use RoundlyConsulting\Teams\Support\TeamsConfig;

final readonly class ApproveJoinRequestAction
{
    public function __construct(
        private AddMemberAction $addMember,
    ) {}

    /**
     * Approve a pending join request, adding the requester as a member.
     *
     * The pending → approved transition is a conditional UPDATE, so of two
     * concurrent responders exactly one wins; it runs in one transaction with the
     * member add, so a refused add (seat cap) leaves the request pending. A
     * requester who already holds an active membership keeps it unchanged.
     *
     * @throws JoinRequestNotPendingException when the request was already resolved
     */
    public function execute(JoinRequest $request, RespondToJoinRequestData $data): Member
    {
        return $request->getConnection()->transaction(function () use ($request, $data): Member {
            $claimed = $request->newQuery()
                ->whereKey($request->getKey())
                ->where('status', JoinRequestStatus::Pending->value)
                ->update([
                    'status' => JoinRequestStatus::Approved->value,
                    'responded_by_type' => $data->responder->getMorphClass(),
                    'responded_by_id' => $data->responder->getKey(),
                    'responded_at' => now(),
                ]);

            if ($claimed === 0) {
                throw JoinRequestNotPendingException::for($request);
            }

            /** @var Team $team */
            $team = $request->team;

            /** @var Model $requester */
            $requester = $request->requester;

            $member = $team->findMember($requester);

            if ($member === null || $member->isExpired()) {
                $member = $this->addMember->execute($team, new AddMemberData(
                    member: $requester,
                    role: $this->role($request, $team, $data),
                ));
            }

            $request->refresh();

            JoinRequestApproved::dispatch($request);

            return $member;
        });
    }

    /**
     * The responder's override, then the requested role, then the team's default
     * role, then `teams.roles.default`.
     */
    private function role(JoinRequest $request, Team $team, RespondToJoinRequestData $data): string
    {
        /** @var string|null $teamDefault */
        $teamDefault = Options::get(DefaultMemberRole::class, $team);

        return $data->role ?? $request->requested_role ?? $teamDefault ?? TeamsConfig::defaultRole();
    }
}
