<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RoundlyConsulting\Teams\Actions\CreateInviteAction;
use RoundlyConsulting\Teams\DataTransferObjects\CreateInviteData;
use RoundlyConsulting\Teams\Events\InviteCreated;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('creates an invite with the default expiry from config', function () {
    Event::fake(InviteCreated::class);
    Carbon::setTestNow('2026-01-01 10:00:00');
    config()->set('teams.invites.expires_after', '7 days');

    $team = Team::factory()->create();

    $invite = app(CreateInviteAction::class)->execute($team, new CreateInviteData(role: 'admin'));

    expect($invite)
        ->toBeInstanceOf(Invite::class)
        ->role->toBe('admin')
        ->and($invite->expires_at->toDateTimeString())->toBe('2026-01-08 10:00:00');

    Event::assertDispatched(fn (InviteCreated $e) => $e->invite->is($invite));

    Carbon::setTestNow();
});

it('honours an explicit expiry, email and inviter', function () {
    config()->set('teams.invites.code_length', 10);

    $team = Team::factory()->create();
    $inviter = User::create();
    $expiresAt = now()->addMonth();

    Str::createRandomStringsUsing(fn (int $length) => str_repeat('a', $length));

    $invite = app(CreateInviteAction::class)->execute($team, new CreateInviteData(
        role: 'user',
        expiresAt: $expiresAt,
        email: 'jane@acme.test',
        invitedBy: $inviter,
    ));

    expect($invite)
        ->email->toBe('jane@acme.test')
        ->code->toBe('aaaaaaaaaa')
        ->invited_by_id->toBe($inviter->id);

    Str::createRandomStringsNormally();
});
