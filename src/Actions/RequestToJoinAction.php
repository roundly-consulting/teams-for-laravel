<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Support\Collection;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\PackageToolkit\Support\Config;
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
use RoundlyConsulting\Teams\Support\TeamsConfig;

final readonly class RequestToJoinAction
{
    public function __construct(
        private AddMemberAction $addMember,
    ) {}

    /**
     * Create a pending join request, honouring the team's JoinPolicy option:
     * an invite-only team rejects the request, and an open team without a
     * require-approval switch auto-approves it — always into the team's default
     * role. A request for any other role is user input, so it stays pending for
     * an owner/admin to decide. Idempotent: an existing pending request for the
     * (team, requester) pair is returned unchanged.
     *
     * A model that already holds an active membership is refused: a join request
     * is never a way to change an existing member's role.
     *
     * When approvers are staged and teams.approvals.enabled is on, a request left
     * pending is routed through the approvals engine; the
     * SyncJoinRequestStatusFromApproval listener mirrors the decision back.
     */
    public function execute(RequestToJoinData $data): JoinRequest
    {
        /** @var JoinPolicy $policy */
        $policy = Options::get(JoinPolicyOption::class, $data->team);

        if ($policy === JoinPolicy::InviteOnly) {
            throw TeamsException::joinPolicyForbidsRequests();
        }

        $membership = $data->team->findMember($data->requester);

        if ($membership !== null && ! $membership->isExpired()) {
            throw TeamsException::alreadyMember($data->team);
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

        $defaultRole = $this->defaultRole($data);

        if ($policy === JoinPolicy::Open && ! $this->requiresApproval($data) && $this->asksForDefaultRole($data, $defaultRole)) {
            $this->autoApprove($request, $data, $defaultRole);
        }

        if ($this->routesThroughApprovals($data) && $request->isPending()) {
            Approvals::request($request)
                ->from($data->approvers)
                ->rule($data->approvalRule ?? $this->configuredRule(), $data->approvalQuorum ?? $this->configuredQuorum())
                ->open();
        }

        return $request;
    }

    private function routesThroughApprovals(RequestToJoinData $data): bool
    {
        return $data->approvers !== [] && Config::boolean('teams.approvals.enabled');
    }

    /**
     * Not set (absent, null or blank) means unanimous; anything that names no `ApprovalRule`
     * throws rather than quietly becoming unanimous, so a typo cannot change who has to sign off.
     */
    private function configuredRule(): ApprovalRule
    {
        return Config::enum('teams.approvals.rule', ApprovalRule::class, ApprovalRule::Unanimous);
    }

    private function configuredQuorum(): ?int
    {
        return TeamsConfig::approvalQuorum();
    }

    private function requiresApproval(RequestToJoinData $data): bool
    {
        return (bool) Options::get(RequireApprovalToJoin::class, $data->team);
    }

    /**
     * The role an auto-approved requester receives: the team's DefaultMemberRole
     * option, then `teams.roles.default`. Never the requested role.
     */
    private function defaultRole(RequestToJoinData $data): string
    {
        /** @var string|null $teamDefault */
        $teamDefault = Options::get(DefaultMemberRole::class, $data->team);

        return $teamDefault ?? TeamsConfig::defaultRole();
    }

    private function asksForDefaultRole(RequestToJoinData $data, string $defaultRole): bool
    {
        return $data->requestedRole === null || $data->requestedRole === $defaultRole;
    }

    private function autoApprove(JoinRequest $request, RequestToJoinData $data, string $role): void
    {
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
