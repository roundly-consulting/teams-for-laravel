<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\DataTransferObjects\RequestToJoinData;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestCreated;
use RoundlyConsulting\Teams\Models\JoinRequest;

final class RequestToJoinAction
{
    /**
     * Create a pending join request. Idempotent: if a pending request already
     * exists for the (team, requester) pair, it is returned unchanged.
     */
    public function execute(RequestToJoinData $data): JoinRequest
    {
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
        ]);

        JoinRequestCreated::dispatch($request);

        return $request;
    }
}
