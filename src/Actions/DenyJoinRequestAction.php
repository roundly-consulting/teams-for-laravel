<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\DataTransferObjects\RespondToJoinRequestData;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestDenied;
use RoundlyConsulting\Teams\Exceptions\JoinRequestNotPendingException;
use RoundlyConsulting\Teams\Models\JoinRequest;

final readonly class DenyJoinRequestAction
{
    /**
     * Deny a pending join request. The pending → denied transition is a
     * conditional UPDATE, so it can never overwrite a concurrent approval.
     *
     * @throws JoinRequestNotPendingException when the request was already resolved
     */
    public function execute(JoinRequest $request, RespondToJoinRequestData $data): JoinRequest
    {
        $claimed = $request->newQuery()
            ->whereKey($request->getKey())
            ->where('status', JoinRequestStatus::Pending->value)
            ->update([
                'status' => JoinRequestStatus::Denied->value,
                'responded_by_type' => $data->responder->getMorphClass(),
                'responded_by_id' => $data->responder->getKey(),
                'responded_at' => now(),
            ]);

        if ($claimed === 0) {
            throw JoinRequestNotPendingException::for($request);
        }

        $request->refresh();

        JoinRequestDenied::dispatch($request);

        return $request;
    }
}
