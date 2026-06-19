<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;

it('resends an invite and prints the new code', function (): void {
    $team = Team::factory()->create();
    $invite = Invite::factory()->for($team)->create(['code' => 'old-code']);

    $this->artisan('teams:invites:resend', ['code' => 'old-code'])
        ->expectsOutputToContain('Invite resent.')
        ->assertSuccessful();

    expect($invite->fresh()?->code)->not->toBe('old-code');
});

it('fails for an unknown code', function (): void {
    $this->artisan('teams:invites:resend', ['code' => 'missing'])
        ->expectsOutputToContain('No invite found')
        ->assertFailed();
});
