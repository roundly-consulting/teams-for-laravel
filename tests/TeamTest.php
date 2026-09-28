<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RoundlyConsulting\Teams\Events\InviteCreated;
use RoundlyConsulting\Teams\Events\TeamMemberAdded;
use RoundlyConsulting\Teams\Events\TeamMemberDeleted;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('has correct casts', function () {
    $team = new Team;

    expect($team->getCasts())->toBe([
        'id' => 'int',
        'meta' => 'collection',
        'is_public' => 'boolean',
        'deleted_at' => 'datetime',
    ]);
});

it('has relationships', function () {
    $team = new Team;

    expect($team)
        ->members()->toBeInstanceOf(HasMany::class)
        ->invites()->toBeInstanceOf(HasMany::class);
});

it('adds member to team', function () {
    Event::fake(TeamMemberAdded::class);

    $team = Team::factory()->create();

    $userInTeam = User::create();

    $member = $team->addMember($userInTeam, 'admin', [
        'foo' => 'bar',
    ]);

    Event::assertDispatched(fn (TeamMemberAdded $e) => $e->member->is($member));

    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'member_id' => $userInTeam->id,
        'member_type' => $userInTeam->getMorphClass(),
        'role' => 'admin',
        'meta' => $this->castAsJson([
            'foo' => 'bar',
        ]),
    ]);
});

it('removes member from team', function () {
    Event::fake(TeamMemberDeleted::class);

    $team = Team::factory()->create();

    $userInTeam = User::create();

    $member = $team->addMember($userInTeam, 'admin', [
        'foo' => 'bar',
    ]);

    $team->removeMember($userInTeam);

    Event::assertDispatched(fn (TeamMemberDeleted $e) => $e->member->is($member));

    $this->assertSoftDeleted($member);

    expect($team->hasMember($userInTeam))->toBeFalse();
});

it('returns false when removing a member that is not on the team', function () {
    $team = Team::factory()->create();

    $userNotInTeam = User::create();

    expect($team->removeMember($userNotInTeam))->toBeFalse();
});

it('checks whether member is in team', function () {
    $team = Team::factory()->create();

    $userInTeam = User::create();
    $userNotInTeam = User::create();

    $team->addMember($userInTeam, 'admin');

    expect($team)
        ->hasMember($userNotInTeam)->toBeFalse()
        ->hasMember($userInTeam)->toBeTrue();
});

it('checks whether member has role', function () {
    $team = Team::factory()->create();

    $userWithAdminRole = User::create();
    $userWithUserRole = User::create();
    $userNotInTeam = User::create();

    $team->addMember($userWithAdminRole, 'admin');
    $team->addMember($userWithUserRole, 'user');

    expect($team)
        ->memberHasRole($userNotInTeam, 'admin')->toBeFalse()
        ->memberHasRole($userWithUserRole, 'admin')->toBeFalse()
        ->memberHasRole($userWithUserRole, 'user')->toBeTrue()
        ->memberHasRole($userWithAdminRole, 'admin')->toBeTrue();
});

it('checks whether user has permission on team', function () {
    $team = Team::factory()->create();

    $userWithAdminRole = User::create();
    $userWithUserRole = User::create();
    $userNotInTeam = User::create();

    $team->addMember($userWithAdminRole, 'admin');
    $team->addMember($userWithUserRole, 'user');

    expect($team)
        ->memberHasPermission($userNotInTeam, '*')->toBeFalse()
        ->memberHasPermission($userWithUserRole, '*')->toBeFalse()
        ->memberHasPermission($userWithUserRole, '*')->toBeFalse()
        ->memberHasPermission($userWithAdminRole, '*')->toBeTrue();
});

it('returns member of team by model', function () {
    $team = Team::factory()->create();

    $user = User::create();
    $userNotInTeam = User::create();

    $team->addMember($user, 'admin');

    expect($team->findMember($user))
        ->toBeInstanceOf(Member::class)
        ->member
        ->is($user)->toBeTrue()
        ->and($team->findMember($userNotInTeam))
        ->toBeNull();
});

it('creates invite with random code', function () {
    Event::fake(InviteCreated::class);

    $team = Team::factory()->create();

    $expiresAt = now()->addDay();

    Str::createRandomStringsUsing(fn () => 'RandomCode');

    $invite = $team->invite('user', $expiresAt, meta: [
        'foo' => 'bar',
    ]);

    Event::assertDispatched(fn (InviteCreated $e) => $e->invite->is($invite));

    expect($invite)
        ->toBeInstanceOf(Invite::class)
        ->code->toBe('RandomCode');

    $this->assertDatabaseHas('team_invites', [
        'team_id' => $team->id,
        'meta' => $this->castAsJson([
            'foo' => 'bar',
        ]),
        'expires_at' => $expiresAt,
        'code' => 'RandomCode',
        'role' => 'user',
    ]);

    Str::createRandomStringsNormally();
});
