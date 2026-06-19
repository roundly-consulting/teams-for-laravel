<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Events\TeamMemberDeleted;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Tests\User;

it('has correct casts', function () {
    $member = new Member;

    expect($member->getCasts())->toBe([
        'id' => 'int',
        'meta' => 'collection',
        'deleted_at' => 'datetime',
    ]);
});

it('has a morph relationship to its member', function () {
    expect((new Member)->member())->toBeInstanceOf(MorphTo::class);
});

it('resolves its role from the registry', function () {
    $team = Team::factory()->create();
    $user = User::create();

    $member = $team->addMember($user, 'admin');

    expect($member->role())
        ->toBeInstanceOf(Role::class)
        ->key->toBe('admin');
});

it('returns null role when the member has none', function () {
    $member = Member::factory()->create(['role' => null]);

    expect($member->role())->toBeNull();
});

it('removes member from team', function () {
    Event::fake(TeamMemberDeleted::class);

    $team = Team::factory()->create();
    $user = User::create();

    $member = $team->addMember($user, 'admin', [
        'foo' => 'bar',
    ]);

    $member->removeFromTeam();

    Event::assertDispatched(fn (TeamMemberDeleted $e) => $e->member->is($member));

    $this->assertSoftDeleted($member);
});
