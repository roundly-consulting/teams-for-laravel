<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Events\InviteAccepted;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('has correct casts', function () {
    $invite = new Invite;

    expect($invite->getCasts())->toBe([
        'id' => 'int',
        'expires_at' => 'datetime',
        'meta' => 'collection',
        'uses' => 'integer',
        'max_uses' => 'integer',
        'deleted_at' => 'datetime',
    ]);
});

it('has relationship to team', function () {
    $invite = new Invite;

    expect($invite->team())->toBeInstanceOf(BelongsTo::class);
});

it('returns whether invite is expired based on expiration date', function () {
    $expired = new Invite(['expires_at' => '2023-01-01 10:00:00']);
    $notExpired = new Invite(['expires_at' => now()->addDay()]);

    expect($expired->isExpired())->toBeTrue()
        ->and($notExpired)->isExpired()->toBeFalse();
});

it('accepts invite and adds member to team', function () {
    Event::fake(InviteAccepted::class);

    $team = Team::factory()->create();
    $user = User::create();

    $invite = $team->invite('admin', now()->addDay());

    expect($team->hasMember($user))->toBeFalse();

    $member = $invite->acceptBy($user);

    Event::assertDispatched(fn (InviteAccepted $e) => $e->invite->is($invite) && $e->member->is($member));

    expect($team->hasMember($user))->toBeTrue()
        ->and($member)->toBeInstanceOf(Member::class);

    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'member_id' => $user->id,
        'member_type' => $user->getMorphClass(),
        'role' => 'admin',
    ]);

    $this->assertSoftDeleted($invite);
});

it('prunes invites that expired over a month ago', function () {
    $stale = Invite::factory()->create(['expires_at' => now()->subMonths(2)]);
    $recent = Invite::factory()->create(['expires_at' => now()->subDays(2)]);

    $matches = $stale->prunable()->get();

    expect($matches->pluck('id')->all())
        ->toContain($stale->id)
        ->not->toContain($recent->id);
});

it('returns query to prunable feature', function () {
    $invite = new Invite;

    Carbon::setTestNow('2023-11-16 10:00:00');

    $query = $invite->prunable();

    expect($query->toSql())
        ->toContain('"expires_at" <= ?')
        ->and($query->getBindings()[0]->timestamp)->toBe(1697450400);

    Carbon::setTestNow();
});
