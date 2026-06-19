<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\DispatchExpiringMembershipsAction;
use RoundlyConsulting\Teams\Events\MembershipExpiringSoon;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;

it('selects only memberships within the window', function (): void {
    $team = Team::factory()->create();
    $within = Member::factory()->for($team)->expiringAt(now()->addDays(3))->create();
    Member::factory()->for($team)->expiringAt(now()->addDays(30))->create();

    $members = app(DispatchExpiringMembershipsAction::class)->execute(7, notify: false);

    expect($members->pluck('id')->all())->toBe([$within->getKey()]);
});

it('excludes already-expired and never-expiring memberships', function (): void {
    $team = Team::factory()->create();
    $within = Member::factory()->for($team)->expiringAt(now()->addDays(2))->create();
    Member::factory()->for($team)->expired()->create();
    Member::factory()->for($team)->create(['expires_at' => null]);

    $members = app(DispatchExpiringMembershipsAction::class)->execute(7, notify: false);

    expect($members->pluck('id')->all())->toBe([$within->getKey()]);
});

it('fires one event per membership when notifying', function (): void {
    Event::fake(MembershipExpiringSoon::class);

    $team = Team::factory()->create();
    Member::factory()->count(2)->for($team)->expiringAt(now()->addDays(3))->create();

    app(DispatchExpiringMembershipsAction::class)->execute(7, notify: true);

    Event::assertDispatchedTimes(MembershipExpiringSoon::class, 2);
});

it('fires no events when not notifying', function (): void {
    Event::fake(MembershipExpiringSoon::class);

    $team = Team::factory()->create();
    Member::factory()->for($team)->expiringAt(now()->addDays(3))->create();

    app(DispatchExpiringMembershipsAction::class)->execute(7, notify: false);

    Event::assertNotDispatched(MembershipExpiringSoon::class);
});

it('honours a custom member model from config', function (): void {
    config()->set('teams.models.member', Member::class);

    $team = Team::factory()->create();
    Member::factory()->for($team)->expiringAt(now()->addDays(3))->create();

    $members = app(DispatchExpiringMembershipsAction::class)->execute(7, notify: false);

    expect($members)->toHaveCount(1);
});

it('agrees with the model scope on the selected rows', function (): void {
    $team = Team::factory()->create();
    Member::factory()->count(3)->for($team)->expiringAt(now()->addDays(4))->create();
    Member::factory()->for($team)->expiringAt(now()->addDays(20))->create();

    $actionIds = app(DispatchExpiringMembershipsAction::class)->execute(7, notify: false)->pluck('id')->sort()->values()->all();
    $scopeIds = Member::query()->expiringWithin(7)->pluck('id')->sort()->values()->all();

    expect($actionIds)->toBe($scopeIds);
});
