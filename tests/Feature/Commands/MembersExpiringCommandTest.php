<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Events\MembershipExpiringSoon;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;

it('lists memberships expiring within the configured window without firing events', function (): void {
    Event::fake(MembershipExpiringSoon::class);
    config()->set('teams.members.expiring_within', 7);

    $team = Team::factory()->create();
    Member::factory()->for($team)->expiringAt(now()->addDays(3))->create();

    $exitCode = Artisan::call('teams:members:expiring');

    expect($exitCode)->toBe(0);

    Event::assertNotDispatched(MembershipExpiringSoon::class);
});

it('fires one event per member when --notify is passed', function (): void {
    Event::fake(MembershipExpiringSoon::class);

    $team = Team::factory()->create();
    Member::factory()->count(2)->for($team)->expiringAt(now()->addDays(3))->create();

    $exitCode = Artisan::call('teams:members:expiring', ['--notify' => true]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Dispatched MembershipExpiringSoon for 2 membership(s).');

    Event::assertDispatchedTimes(MembershipExpiringSoon::class, 2);
});

it('narrows the window with --days', function (): void {
    $team = Team::factory()->create();
    Member::factory()->for($team)->expiringAt(now()->addDays(2))->create();
    Member::factory()->for($team)->expiringAt(now()->addDays(5))->create();

    $exitCode = Artisan::call('teams:members:expiring', ['--days' => 3]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->not->toContain('No memberships are expiring');
});

it('reports an empty result when nothing is expiring', function (): void {
    $exitCode = Artisan::call('teams:members:expiring', ['--days' => 3]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('No memberships are expiring within 3 day(s).');
});

it('is registered with the application', function (): void {
    expect(Artisan::all())->toHaveKey('teams:members:expiring');
});
