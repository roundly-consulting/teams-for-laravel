<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Handles;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Teams\Actions\ApproveJoinRequestAction;
use RoundlyConsulting\Teams\Actions\DenyJoinRequestAction;
use RoundlyConsulting\Teams\Actions\RequestToJoinAction;
use RoundlyConsulting\Teams\DataTransferObjects\RequestToJoinData;
use RoundlyConsulting\Teams\DataTransferObjects\RespondToJoinRequestData;
use RoundlyConsulting\Teams\Enums\TeamOperation;
use RoundlyConsulting\Teams\Exceptions\JoinRequestNotFoundException;
use RoundlyConsulting\Teams\Exceptions\JoinRequestNotPendingException;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * `Teams::for($team)->joinRequests()` — requests to join the team.
 *
 * Immutable: `requireApprovalFrom()`, `rule()` and `quorum()` return a new handle
 * carrying the staged multi-admin sign-off for `open()`. `approve()` and `deny()`
 * refuse a request that belongs to another team.
 */
final readonly class TeamJoinRequests
{
    /**
     * @param  list<Model>  $approvers
     */
    public function __construct(
        private TeamsManager $manager,
        private Team $team,
        private array $approvers = [],
        private ?ApprovalRule $rule = null,
        private ?int $quorum = null,
    ) {}

    /**
     * Route the next `open()` through the approvals engine: the join request stays
     * pending and an approval request is opened for these approvers (when
     * `teams.approvals.enabled` is on).
     *
     * @param  Model|iterable<int, Model>  $approvers
     */
    public function requireApprovalFrom(Model|iterable $approvers): self
    {
        $list = $approvers instanceof Model ? [$approvers] : [...$approvers];

        return new self($this->manager, $this->team, $list, $this->rule, $this->quorum);
    }

    /**
     * The approval rule for a routed request; defaults to `teams.approvals.rule`.
     */
    public function rule(ApprovalRule $rule): self
    {
        return new self($this->manager, $this->team, $this->approvers, $rule, $this->quorum);
    }

    /**
     * The approval quorum for a routed request; defaults to `teams.approvals.quorum`.
     */
    public function quorum(?int $quorum): self
    {
        return new self($this->manager, $this->team, $this->approvers, $this->rule, $quorum);
    }

    /**
     * Open a join request. Honours the team's join policy: an invite-only team
     * refuses, an open team without the require-approval switch auto-approves.
     * Idempotent per requester while a request is pending.
     *
     * @param  array<string, mixed>  $meta
     */
    public function open(
        Model $requester,
        ?string $requestedRole = null,
        ?string $message = null,
        array $meta = [],
        ?CarbonInterface $expiresAt = null,
    ): JoinRequest {
        return $this->manager->perform(
            TeamOperation::RequestToJoin,
            RequestToJoinAction::class,
            fn (RequestToJoinAction $action): JoinRequest => $action->execute(new RequestToJoinData(
                team: $this->team,
                requester: $requester,
                requestedRole: $requestedRole,
                message: $message,
                meta: $meta,
                expiresAt: $expiresAt,
                approvers: $this->approvers,
                approvalRule: $this->rule,
                approvalQuorum: $this->quorum,
            )),
            ['team' => $this->team, 'requester' => $requester, 'role' => $requestedRole],
        );
    }

    /**
     * Approve a pending request, adding the requester as a member. `$role` defaults
     * to the requested role, then the team's default role, then `teams.roles.default`.
     * A requester who already holds an active membership keeps it unchanged.
     *
     * @throws JoinRequestNotFoundException when the request belongs to another team
     * @throws JoinRequestNotPendingException when the request was already resolved
     */
    public function approve(JoinRequest $request, Model $by, ?string $role = null): Member
    {
        $this->guard($request);

        return $this->manager->perform(
            TeamOperation::ApproveJoinRequest,
            ApproveJoinRequestAction::class,
            static fn (ApproveJoinRequestAction $action): Member => $action->execute(
                $request,
                new RespondToJoinRequestData(responder: $by, role: $role),
            ),
            ['team' => $this->team, 'request' => $request, 'by' => $by, 'role' => $role],
        );
    }

    /**
     * Deny a pending request.
     *
     * @throws JoinRequestNotFoundException when the request belongs to another team
     * @throws JoinRequestNotPendingException when the request was already resolved
     */
    public function deny(JoinRequest $request, Model $by): JoinRequest
    {
        $this->guard($request);

        return $this->manager->perform(
            TeamOperation::DenyJoinRequest,
            DenyJoinRequestAction::class,
            static fn (DenyJoinRequestAction $action): JoinRequest => $action->execute(
                $request,
                new RespondToJoinRequestData(responder: $by),
            ),
            ['team' => $this->team, 'request' => $request, 'by' => $by],
        );
    }

    /**
     * The team's pending join requests.
     *
     * @return Collection<int, JoinRequest>
     */
    public function pending(): Collection
    {
        return $this->team->joinRequests()->pending()->get();
    }

    private function guard(JoinRequest $request): void
    {
        if ((string) $request->team_id !== (string) $this->team->getKey()) {
            throw JoinRequestNotFoundException::inTeam($request);
        }
    }
}
