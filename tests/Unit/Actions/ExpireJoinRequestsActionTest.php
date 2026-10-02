<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\ExpireJoinRequestsAction;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Events\JoinRequestExpired;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Team;

it('auto-declines an expired pending request and fires the event', function (): void {
    Event::fake(JoinRequestExpired::class);

    $team = Team::factory()->create();
    $request = JoinRequest::factory()->for($team)->expired()->create();

    $count = app(ExpireJoinRequestsAction::class)->execute();

    $request->refresh();

    expect($count)->toBe(1)
        ->and($request->status)->toBe(JoinRequestStatus::Denied)
        ->and($request->responded_by_type)->toBeNull()
        ->and($request->responded_by_id)->toBeNull()
        ->and($request->responded_at)->not->toBeNull();

    Event::assertDispatchedTimes(JoinRequestExpired::class, 1);
    Event::assertDispatched(fn (JoinRequestExpired $e) => $e->request->is($request));
});

it('never touches a request with a null expiry', function (): void {
    Event::fake(JoinRequestExpired::class);

    $request = JoinRequest::factory()->create(['expires_at' => null]);

    $count = app(ExpireJoinRequestsAction::class)->execute();

    expect($count)->toBe(0)
        ->and($request->fresh()?->status)->toBe(JoinRequestStatus::Pending);

    Event::assertNotDispatched(JoinRequestExpired::class);
});

it('never touches a future-dated pending request', function (): void {
    $request = JoinRequest::factory()->expiringWithin(5)->create();

    $count = app(ExpireJoinRequestsAction::class)->execute();

    expect($count)->toBe(0)
        ->and($request->fresh()?->status)->toBe(JoinRequestStatus::Pending);
});

it('never re-processes an already-denied request', function (): void {
    $request = JoinRequest::factory()->expired()->create([
        'status' => JoinRequestStatus::Denied,
    ]);

    $count = app(ExpireJoinRequestsAction::class)->execute();

    expect($count)->toBe(0)
        ->and($request->fresh()?->status)->toBe(JoinRequestStatus::Denied);
});

it('never re-processes an already-approved request', function (): void {
    $request = JoinRequest::factory()->expired()->create([
        'status' => JoinRequestStatus::Approved,
    ]);

    $count = app(ExpireJoinRequestsAction::class)->execute();

    expect($count)->toBe(0)
        ->and($request->fresh()?->status)->toBe(JoinRequestStatus::Approved);
});

it('returns the count across multiple expired requests', function (): void {
    JoinRequest::factory()->count(3)->expired()->create();
    JoinRequest::factory()->expiringWithin(5)->create();

    $count = app(ExpireJoinRequestsAction::class)->execute();

    expect($count)->toBe(3);
});

it('resolves the join_request model from config', function (): void {
    config()->set('teams.models.join_request', JoinRequest::class);

    JoinRequest::factory()->expired()->create();

    $count = app(ExpireJoinRequestsAction::class)->execute();

    expect($count)->toBe(1);
});

it('does not overwrite a request that was approved between the scan and the update', function (): void {
    Event::fake(JoinRequestExpired::class);

    $team = Team::factory()->create();
    $request = JoinRequest::factory()->for($team)->expired()->create();

    // Another process approves the row right after this one read it as pending.
    JoinRequest::retrieved(function (JoinRequest $read): void {
        JoinRequest::query()->whereKey($read->getKey())->update(['status' => JoinRequestStatus::Approved->value]);
    });

    $count = app(ExpireJoinRequestsAction::class)->execute();

    expect($count)->toBe(0)
        ->and($request->fresh()?->status)->toBe(JoinRequestStatus::Approved);
    Event::assertNotDispatched(JoinRequestExpired::class);
});
