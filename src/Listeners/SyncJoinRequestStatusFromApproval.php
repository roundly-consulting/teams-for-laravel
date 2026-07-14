<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Listeners;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Teams\Actions\ApproveJoinRequestAction;
use RoundlyConsulting\Teams\Actions\DenyJoinRequestAction;
use RoundlyConsulting\Teams\DataTransferObjects\RespondToJoinRequestData;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Support\JoinRequestModel;

/**
 * Mirrors a join request's approval-request resolution onto its own status, so a
 * multi-admin sign-off (a direct decision, quorum reached, a staged pipeline
 * clearing, or a rejection) drives the request Approved/Denied through the same
 * actions and events as the native single-responder path.
 *
 * Additive and opt-in: it only acts when teams.approvals.enabled is on and the
 * subject is a join request. Idempotency is inherited from the underlying
 * actions (a non-pending request is a no-op), so a double resolution never adds
 * a member twice.
 */
final class SyncJoinRequestStatusFromApproval
{
    public function __construct(
        private readonly ApproveJoinRequestAction $approve,
        private readonly DenyJoinRequestAction $deny,
    ) {}

    public function handle(ApprovalRequestResolved $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $request = $event->request;
        $subject = $request->subject;

        $model = JoinRequestModel::class();

        if (! $subject instanceof $model) {
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

        $data = new RespondToJoinRequestData(responder: $responder);

        if ($target === JoinRequestStatus::Approved) {
            $this->approve->execute($subject, $data);

            return;
        }

        $this->deny->execute($subject, $data);
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
