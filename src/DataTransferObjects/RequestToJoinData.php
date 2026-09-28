<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Teams\Models\Team;

final readonly class RequestToJoinData
{
    /**
     * `$approvers` opt the request into multi-admin sign-off through the approvals
     * engine (when `teams.approvals.enabled` is on); `$approvalRule` and
     * `$approvalQuorum` default to `teams.approvals.rule` / `teams.approvals.quorum`.
     *
     * @param  array<string, mixed>  $meta
     * @param  list<Model>  $approvers
     */
    public function __construct(
        public Team $team,
        public Model $requester,
        public ?string $requestedRole = null,
        public ?string $message = null,
        public array $meta = [],
        public ?CarbonInterface $expiresAt = null,
        public array $approvers = [],
        public ?ApprovalRule $approvalRule = null,
        public ?int $approvalQuorum = null,
    ) {}
}
