<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\DataTransferObjects\RespondToJoinRequestData;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestDenied;
use RoundlyConsulting\Teams\Models\JoinRequest;

final readonly class DenyJoinRequestAction
{
    /**
     * Deny a pending join request. A non-pending request is returned unchanged
     * to guard against a double response.
     */
    public function execute(JoinRequest $request, RespondToJoinRequestData $data): JoinRequest
    {
        if (! $request->isPending()) {
            return $request;
        }

        $request->update([
            'status' => JoinRequestStatus::Denied,
            'responded_by_type' => $data->responder->getMorphClass(),
            'responded_by_id' => $data->responder->getKey(),
            'responded_at' => now(),
        ]);

        JoinRequestDenied::dispatch($request);

        return $request;
    }
}
