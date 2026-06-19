<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\AddMemberAction;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\Events\TeamMemberAdded;
use RoundlyConsulting\Teams\Events\TeamMemberRoleChanged;
use RoundlyConsulting\Teams\Models\Invite;
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

it('persists an expiry on create', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $expiresAt = now()->addDays(30);

    $member = app(AddMemberAction::class)->execute($team, new AddMemberData(
        member: $user,
        role: 'admin',
        expiresAt: $expiresAt,
    ));

    expect($member->expires_at->toDateString())->toBe($expiresAt->toDateString());
});

it('keeps an existing expiry when re-adding without one', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $expiresAt = now()->addDays(30);

    app(AddMemberAction::class)->execute($team, new AddMemberData(member: $user, role: 'admin', expiresAt: $expiresAt));
    $second = app(AddMemberAction::class)->execute($team, new AddMemberData(member: $user, role: 'admin'));

    expect($second->expires_at)->not->toBeNull()
        ->and($second->expires_at->toDateString())->toBe($expiresAt->toDateString());
});

it('overwrites the expiry when re-adding with a new one', function () {
    $team = Team::factory()->create();
    $user = User::create();

    app(AddMemberAction::class)->execute($team, new AddMemberData(member: $user, role: 'admin', expiresAt: now()->addDays(5)));
    $second = app(AddMemberAction::class)->execute($team, new AddMemberData(member: $user, role: 'admin', expiresAt: now()->addDays(60)));

    expect($second->expires_at->toDateString())->toBe(now()->addDays(60)->toDateString());
});

it('adds a member without an accepted invite by default', function () {
    $team = Team::factory()->create();
    $user = User::create();

    $member = app(AddMemberAction::class)->execute($team, new AddMemberData(member: $user, role: 'admin'));

    expect($member->accepted_invite_id)->toBeNull();
});

it('stamps the accepted invite when supplied on create', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $invite = Invite::factory()->for($team)->create();

    $member = app(AddMemberAction::class)->execute($team, new AddMemberData(
        member: $user,
        role: 'admin',
        acceptedInviteId: (int) $invite->getKey(),
    ));

    expect($member->accepted_invite_id)->toBe($invite->getKey());
});

it('preserves the original accepted invite on idempotent re-add', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $first = Invite::factory()->for($team)->create();
    $second = Invite::factory()->for($team)->create();

    app(AddMemberAction::class)->execute($team, new AddMemberData(
        member: $user,
        role: 'admin',
        acceptedInviteId: (int) $first->getKey(),
    ));

    $reAdded = app(AddMemberAction::class)->execute($team, new AddMemberData(
        member: $user,
        role: 'admin',
        acceptedInviteId: (int) $second->getKey(),
    ));

    expect($reAdded->accepted_invite_id)->toBe($first->getKey());
});

it('stamps the accepted invite on re-add when the membership had none', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $invite = Invite::factory()->for($team)->create();

    app(AddMemberAction::class)->execute($team, new AddMemberData(member: $user, role: 'admin'));

    $reAdded = app(AddMemberAction::class)->execute($team, new AddMemberData(
        member: $user,
        role: 'admin',
        acceptedInviteId: (int) $invite->getKey(),
    ));

    expect($reAdded->accepted_invite_id)->toBe($invite->getKey());
});
