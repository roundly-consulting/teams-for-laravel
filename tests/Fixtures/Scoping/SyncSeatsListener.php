<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping;

use Illuminate\Contracts\Queue\ShouldQueue;
use RoundlyConsulting\Teams\Contracts\WithoutTeamContext;

/**
 * A queued listener marked to carry no team context.
 */
final class SyncSeatsListener implements ShouldQueue, WithoutTeamContext
{
    public function handle(TeamLeft $event): void
    {
        TeamArgumentJob::record($event->teamId);
    }
}
