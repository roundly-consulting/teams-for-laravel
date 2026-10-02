<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Listeners;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Exceptions\JoinRequestNotPendingException;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Support\JoinRequestModel;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * Mirrors a join request's approval-request resolution onto its own status, so a
 * multi-admin sign-off (a direct decision, quorum reached, a staged pipeline
 * clearing, or a rejection) drives the request Approved/Denied through the same
 * actions and events as the native single-responder path — through the manager,
 * so `Teams::fake()` records engine-driven decisions too.
 *
 * Additive and opt-in: it only acts when teams.approvals.enabled is on and the
 * subject is a join request. A request that is no longer pending (resolved by
 * hand, by expiry, or by a concurrent responder) is left as it is, so a double
 * resolution never adds a member twice and never overturns a human decision.
 */
final class SyncJoinRequestStatusFromApproval
{
    public function __construct(
        private readonly TeamsManager $teams,
    ) {}

    public function handle(ApprovalRequestResolved $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $request = $event->request;
        $subject = $request->subject;

        $model = JoinRequestModel::class();

        // A request already resolved by hand (or by expiry) keeps that outcome.
        if (! $subject instanceof $model || ! $subject->isPending()) {
            return;
        }

        $target = $this->map($request->status);

        if ($target === null) {
            return;
        }

        $responder = $this->decidingActor($request);

        if (! $responder instanceof Model) {
            return;
        }

        /** @var Team $team */
        $team = $subject->team;

        $requests = $this->teams->for($team)->joinRequests();

        try {
            if ($target === JoinRequestStatus::Approved) {
                $requests->approve($subject, by: $responder);

                return;
            }

            $requests->deny($subject, by: $responder);
        } catch (JoinRequestNotPendingException) {
            // Lost a race with a concurrent responder: their decision stands.
        }
    }

    private function map(ApprovalStatus $status): ?JoinRequestStatus
    {
        return match ($status) {
            ApprovalStatus::Approved => JoinRequestStatus::Approved,
            ApprovalStatus::Rejected => JoinRequestStatus::Denied,
            ApprovalStatus::Pending, ApprovalStatus::Cancelled, ApprovalStatus::Expired => null,
        };
    }

    private function decidingActor(ApprovalRequest $request): ?Model
    {
        $decision = $request->decisions()
            ->whereIn('status', [ApprovalStatus::Approved, ApprovalStatus::Rejected])
            ->latest('id')
            ->first();

        $actor = $decision?->actor;

        return $actor instanceof Model ? $actor : null;
    }

    private function enabled(): bool
    {
        return (bool) config('teams.approvals.enabled', false);
    }
}
