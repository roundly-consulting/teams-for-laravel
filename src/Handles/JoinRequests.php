<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Handles;

use RoundlyConsulting\Teams\Actions\ExpireJoinRequestsAction;
use RoundlyConsulting\Teams\Enums\TeamOperation;
use RoundlyConsulting\Teams\TeamsManager;

/**
 * `Teams::joinRequests()` — join requests across teams.
 */
final readonly class JoinRequests
{
    public function __construct(
        private TeamsManager $manager,
    ) {}

    /**
     * Auto-decline pending join requests whose expiry has passed, firing
     * `JoinRequestExpired` for each.
     *
     * @return int the number of requests declined
     */
    public function expire(): int
    {
        return $this->manager->perform(
            TeamOperation::ExpireJoinRequests,
            ExpireJoinRequestsAction::class,
            static fn (ExpireJoinRequestsAction $action): int => $action->execute(),
        );
    }
}
