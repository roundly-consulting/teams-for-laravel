<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestExpired;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('runs a request-to-approve flow end to end', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $admin = User::create();

    $request = Teams::requestToJoin($team, $user, requestedRole: 'admin', message: 'Hi');

    expect($request->status)->toBe(JoinRequestStatus::Pending);

    $member = Teams::for($team)->approveJoinRequest($request, $admin);

    expect($member->role)->toBe('admin')
        ->and($team->hasMember($user))->toBeTrue()
        ->and($request->fresh()?->status)->toBe(JoinRequestStatus::Approved);
});

it('runs a request-to-deny flow end to end', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $admin = User::create();

    $request = Teams::requestToJoin($team, $user);

    Teams::for($team)->denyJoinRequest($request, $admin);

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Denied)
        ->and($team->hasMember($user))->toBeFalse();
});

it('exposes pending requests on the team relation', function (): void {
    $team = Team::factory()->create();
    Teams::requestToJoin($team, User::create());

    expect($team->joinRequests()->pending()->count())->toBe(1);
});

it('expires a request with a past expiry on prune, firing the event', function (): void {
    Event::fake(JoinRequestExpired::class);

    $team = Team::factory()->create();
    $user = User::create();

    $request = Teams::requestToJoin($team, $user, expiresAt: now()->subDay());

    expect($request->status)->toBe(JoinRequestStatus::Pending);

    Artisan::call('teams:join-requests:prune');

    expect($request->fresh()?->status)->toBe(JoinRequestStatus::Denied)
        ->and($team->hasMember($user))->toBeFalse();

    Event::assertDispatched(fn (JoinRequestExpired $e) => $e->request->is($request));
});
