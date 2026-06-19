<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Teams\Models\Invite;

it('prunes expired invites and reports the count', function () {
    Invite::factory()->create(['expires_at' => now()->subMonths(2)]);
    Invite::factory()->create(['expires_at' => now()->addWeek()]);

    $exitCode = Artisan::call('teams:invites:prune');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Pruned 1 expired invite(s).')
        ->and(Invite::withTrashed()->count())->toBe(1);
});
