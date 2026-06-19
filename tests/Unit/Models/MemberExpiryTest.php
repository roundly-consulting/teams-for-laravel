<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Roles;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(fn () => Roles::register('manager', 'Manager', ['manage-billing']));

it('reports expiry only when expires_at is in the past', function (): void {
    expect((new Member)->isExpired())->toBeFalse()
        ->and((new Member(['expires_at' => now()->subDay()]))->isExpired())->toBeTrue()
        ->and((new Member(['expires_at' => now()->addDay()]))->isExpired())->toBeFalse();
});

it('partitions active and expired with the scopes', function (): void {
    $team = Team::factory()->create();
    Member::factory()->for($team)->create(['expires_at' => null]);
    Member::factory()->for($team)->expiringAt(now()->addDay())->create();
    Member::factory()->for($team)->expired()->create();

    expect(Member::query()->active()->count())->toBe(2)
        ->and(Member::query()->expired()->count())->toBe(1);
});

it('resolves no role and no permission for an expired member', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'manager', expiresAt: now()->subDay());

    expect($team->memberHasPermission($user, 'manage-billing'))->toBeFalse()
        ->and($team->findMember($user)?->role())->toBeNull();
});

it('keeps membership existence checks true while expired', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'manager', expiresAt: now()->subDay());

    expect($team->hasMember($user))->toBeTrue()
        ->and($team->memberHasRole($user, 'manager'))->toBeTrue();
});

it('still resolves role and permission for a non-expired member', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'manager', expiresAt: now()->addDay());

    expect($team->memberHasPermission($user, 'manage-billing'))->toBeTrue();
});

it('exposes a prunable query targeting long-expired members', function (): void {
    config()->set('teams.members.prune_after', '30 days');

    $team = Team::factory()->create();
    Member::factory()->for($team)->expiringAt(now()->subDays(40))->create();
    Member::factory()->for($team)->expiringAt(now()->subDays(5))->create();

    expect((new Member)->prunable()->count())->toBe(1);
});

it('scopes memberships expiring within the window, excluding edges and excluded rows', function (): void {
    $team = Team::factory()->create();

    $within = Member::factory()->for($team)->expiringAt(now()->addDays(3))->create();
    Member::factory()->for($team)->expiringAt(now()->addDays(10))->create();   // beyond window
    Member::factory()->for($team)->expired()->create();                        // already expired
    Member::factory()->for($team)->create(['expires_at' => null]);             // never expires

    $ids = Member::query()->expiringWithin(7)->pluck('id')->all();

    expect($ids)->toBe([$within->getKey()]);
});

it('includes memberships expiring exactly at the window boundaries', function (): void {
    $team = Team::factory()->create();

    $atUpper = Member::factory()->for($team)->expiringAt(now()->addDays(7))->create();
    $justInside = Member::factory()->for($team)->expiringAt(now()->addSeconds(5))->create();
    Member::factory()->for($team)->expiringAt(now()->addDays(7)->addMinute())->create(); // just outside

    $ids = Member::query()->expiringWithin(7)->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$atUpper, $justInside])->map->getKey()->sort()->values()->all());
});
