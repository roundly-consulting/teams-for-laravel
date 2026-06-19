<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestExpired;
use RoundlyConsulting\Teams\Models\JoinRequest;

final class ExpireJoinRequestsAction
{
    /**
     * Auto-decline pending join requests whose expiry has passed, firing
     * JoinRequestExpired per row. A system-decided decline records a null
     * responder. Returns the number of requests declined.
     */
    public function execute(): int
    {
        /** @var class-string<JoinRequest> $model */
        $model = config('teams.models.join_request', JoinRequest::class);

        $count = 0;

        $model::query()
            ->expiredPending()
            ->each(function (JoinRequest $request) use (&$count): void {
                $request->update([
                    'status' => JoinRequestStatus::Denied,
                    'responded_by_type' => null,
                    'responded_by_id' => null,
                    'responded_at' => now(),
                ]);

                JoinRequestExpired::dispatch($request);

                $count++;
            });

        return $count;
    }
}
