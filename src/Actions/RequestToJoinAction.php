<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Support\Collection;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\DataTransferObjects\RequestToJoinData;
use RoundlyConsulting\Teams\Enums\JoinPolicy;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestApproved;
use RoundlyConsulting\Teams\Events\JoinRequestCreated;
use RoundlyConsulting\Teams\Exceptions\TeamsException;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Options\DefaultMemberRole;
use RoundlyConsulting\Teams\Options\JoinPolicy as JoinPolicyOption;
use RoundlyConsulting\Teams\Options\RequireApprovalToJoin;

final class RequestToJoinAction
{
    public function __construct(
        private readonly AddMemberAction $addMember,
    ) {}

    /**
     * Create a pending join request, honouring the team's JoinPolicy option:
     * an invite-only team rejects the request, and an open team without a
     * require-approval switch auto-approves it. Idempotent: an existing pending
     * request for the (team, requester) pair is returned unchanged.
     */
    public function execute(RequestToJoinData $data): JoinRequest
    {
        /** @var JoinPolicy $policy */
        $policy = Options::get(JoinPolicyOption::class, $data->team);

        if ($policy === JoinPolicy::InviteOnly) {
            throw TeamsException::joinPolicyForbidsRequests();
        }

        $existing = $data->team->joinRequests()
            ->whereMorphedTo('requester', $data->requester)
            ->where('status', JoinRequestStatus::Pending->value)
            ->first();

        if ($existing instanceof JoinRequest) {
            return $existing;
        }

        /** @var JoinRequest $request */
        $request = $data->team->joinRequests()->create([
            'requester_type' => $data->requester->getMorphClass(),
            'requester_id' => $data->requester->getKey(),
            'requested_role' => $data->requestedRole,
            'status' => JoinRequestStatus::Pending,
            'message' => $data->message,
            'meta' => new Collection($data->meta),
            'expires_at' => $data->expiresAt,
        ]);

        JoinRequestCreated::dispatch($request);

        if ($policy === JoinPolicy::Open && ! $this->requiresApproval($data)) {
            $this->autoApprove($request, $data);
        }

        return $request;
    }

    private function requiresApproval(RequestToJoinData $data): bool
    {
        return (bool) Options::get(RequireApprovalToJoin::class, $data->team);
    }

    private function autoApprove(JoinRequest $request, RequestToJoinData $data): void
    {
        /** @var string $configDefault */
        $configDefault = config('teams.roles.default', 'member');

        /** @var string|null $teamDefault */
        $teamDefault = Options::get(DefaultMemberRole::class, $data->team);

        $role = $data->requestedRole ?? $teamDefault ?? $configDefault;

        $this->addMember->execute($data->team, new AddMemberData(
            member: $data->requester,
            role: $role,
        ));

        $request->update([
            'status' => JoinRequestStatus::Approved,
            'responded_at' => now(),
        ]);

        JoinRequestApproved::dispatch($request);
    }
}
