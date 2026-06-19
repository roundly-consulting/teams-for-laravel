<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;

it('prunes expired members and reports the count', function (): void {
    config()->set('teams.members.prune_after', '30 days');

    $team = Team::factory()->create();
    Member::factory()->for($team)->expiringAt(now()->subDays(40))->create();

    $this->artisan('teams:members:prune')
        ->expectsOutput('Pruned 1 expired member(s).')
        ->assertSuccessful();
});
