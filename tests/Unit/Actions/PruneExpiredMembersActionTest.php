<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\PruneExpiredMembersAction;
use RoundlyConsulting\Teams\Events\MembershipExpired;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;

it('force-deletes members expired beyond the retention window and fires events', function (): void {
    Event::fake(MembershipExpired::class);
    config()->set('teams.members.prune_after', '30 days');

    $team = Team::factory()->create();
    $stale = Member::factory()->for($team)->expiringAt(now()->subDays(40))->create();
    $recent = Member::factory()->for($team)->expiringAt(now()->subDays(5))->create();
    $active = Member::factory()->for($team)->create(['expires_at' => null]);

    $pruned = app(PruneExpiredMembersAction::class)->execute();

    expect($pruned)->toBe(1)
        ->and(Member::withTrashed()->find($stale->getKey()))->toBeNull()
        ->and(Member::find($recent->getKey()))->not->toBeNull()
        ->and(Member::find($active->getKey()))->not->toBeNull();

    Event::assertDispatchedTimes(MembershipExpired::class, 1);
});

it('returns zero when nothing is prunable', function (): void {
    expect(app(PruneExpiredMembersAction::class)->execute())->toBe(0);
});

it('fires MembershipExpired when model:prune collects an expired membership', function (): void {
    Event::fake(MembershipExpired::class);
    config()->set('teams.members.prune_after', '30 days');

    $team = Team::factory()->create();
    $stale = Member::factory()->for($team)->expiringAt(now()->subDays(40))->create();
    $recent = Member::factory()->for($team)->expiringAt(now()->subDays(5))->create();

    Artisan::call('model:prune', ['--model' => [Member::class]]);

    expect(Member::withTrashed()->find($stale->getKey()))->toBeNull()
        ->and(Member::find($recent->getKey()))->not->toBeNull();

    Event::assertDispatchedTimes(MembershipExpired::class, 1);
    Event::assertDispatched(fn (MembershipExpired $event): bool => $event->member->is($stale));
});
