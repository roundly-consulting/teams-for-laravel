<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestExpired;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Support\JoinRequestModel;

final readonly class ExpireJoinRequestsAction
{
    /**
     * Auto-decline pending join requests whose expiry has passed, firing
     * JoinRequestExpired per row. A system-decided decline records a null
     * responder. Returns the number of requests declined.
     */
    public function execute(): int
    {
        $count = 0;

        JoinRequestModel::query()
            ->expiredPending()
            ->each(function (JoinRequest $request) use (&$count): void {
                // Conditional on still being pending: a responder who approved the
                // row after this scan read it keeps their decision.
                $claimed = $request->newQuery()
                    ->whereKey($request->getKey())
                    ->where('status', JoinRequestStatus::Pending->value)
                    ->update([
                        'status' => JoinRequestStatus::Denied->value,
                        'responded_by_type' => null,
                        'responded_by_id' => null,
                        'responded_at' => now(),
                    ]);

                if ($claimed === 0) {
                    return;
                }

                $request->refresh();

                JoinRequestExpired::dispatch($request);

                $count++;
            });

        return $count;
    }
}
