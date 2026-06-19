<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Actions\PruneInvitesAction;
use RoundlyConsulting\Teams\Models\Invite;

it('force-deletes invites that expired over a month ago', function () {
    $stale = Invite::factory()->create(['expires_at' => now()->subMonths(2)]);
    $recent = Invite::factory()->create(['expires_at' => now()->subDays(2)]);

    $pruned = app(PruneInvitesAction::class)->execute();

    expect($pruned)->toBe(1)
        ->and(Invite::withTrashed()->find($stale->id))->toBeNull()
        ->and(Invite::find($recent->id))->not->toBeNull();
});
