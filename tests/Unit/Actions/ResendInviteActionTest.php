<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\ResendInviteAction;
use RoundlyConsulting\Teams\Events\InviteResent;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;

afterEach(fn () => Carbon::setTestNow());

it('rotates the code, extends expiry and fires an event', function (): void {
    Event::fake(InviteResent::class);
    Carbon::setTestNow('2026-06-01 12:00:00');

    $team = Team::factory()->create();
    $invite = Invite::factory()->for($team)->create([
        'code' => 'original-code',
        'role' => 'admin',
        'expires_at' => now()->subDay(),
    ]);

    $resent = app(ResendInviteAction::class)->execute($invite);

    expect($resent->code)->not->toBe('original-code')
        ->and($resent->role)->toBe('admin')
        ->and($resent->expires_at->isFuture())->toBeTrue();

    Event::assertDispatched(fn (InviteResent $e) => $e->invite->is($invite));
});

it('is callable from the model', function (): void {
    $invite = Invite::factory()->create(['code' => 'before']);

    expect($invite->resend()->code)->not->toBe('before');
});
