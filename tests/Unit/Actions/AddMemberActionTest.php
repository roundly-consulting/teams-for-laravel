<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\AddMemberAction;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\Events\TeamMemberAdded;
use RoundlyConsulting\Teams\Events\TeamMemberRoleChanged;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('adds a new member and dispatches the event', function () {
    Event::fake([TeamMemberAdded::class, TeamMemberRoleChanged::class]);

    $team = Team::factory()->create();
    $user = User::create();

    $member = app(AddMemberAction::class)->execute($team, new AddMemberData(
        member: $user,
        role: 'admin',
        meta: ['foo' => 'bar'],
    ));

    expect($member)->toBeInstanceOf(Member::class)->role->toBe('admin');

    Event::assertDispatched(fn (TeamMemberAdded $e) => $e->member->is($member));
    Event::assertNotDispatched(TeamMemberRoleChanged::class);
});

it('is idempotent and returns the existing membership without changing the role', function () {
    Event::fake([TeamMemberAdded::class, TeamMemberRoleChanged::class]);

    $team = Team::factory()->create();
    $user = User::create();

    $first = app(AddMemberAction::class)->execute($team, new AddMemberData(member: $user, role: 'admin'));
    $second = app(AddMemberAction::class)->execute($team, new AddMemberData(member: $user, role: 'admin'));

    expect($second->is($first))->toBeTrue()
        ->and($team->members()->count())->toBe(1);

    Event::assertDispatchedTimes(TeamMemberAdded::class, 1);
    Event::assertNotDispatched(TeamMemberRoleChanged::class);
});

it('updates the role when re-adding an existing member with a different role', function () {
    Event::fake([TeamMemberRoleChanged::class]);

    $team = Team::factory()->create();
    $user = User::create();

    app(AddMemberAction::class)->execute($team, new AddMemberData(member: $user, role: 'user'));
    $updated = app(AddMemberAction::class)->execute($team, new AddMemberData(member: $user, role: 'admin'));

    expect($updated->role)->toBe('admin')
        ->and($team->members()->count())->toBe(1);

    Event::assertDispatched(fn (TeamMemberRoleChanged $e) => $e->member->is($updated) && $e->previousRole === 'user');
});
