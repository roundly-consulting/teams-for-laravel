<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Tests\User;

it('has relationship to teams', function () {
    $user = new User;

    expect($user->teams())->toBeInstanceOf(MorphMany::class);
});

it('checks whether user belongs to team', function () {
    $team = Team::factory()->create();
    $userInTeam = User::create();
    $userNotInTeam = User::create();

    $team->addMember($userInTeam, 'admin');

    expect($userInTeam)
        ->belongsToTeam($team)
        ->toBeTrue()
        ->and($userNotInTeam)
        ->belongsToTeam($team)
        ->toBeFalse();
});

it('returns team role of user', function () {
    $team = Team::factory()->create();
    $userWithAdminRole = User::create();
    $userWithUserRole = User::create();

    $team->addMember($userWithAdminRole, 'admin');
    $team->addMember($userWithUserRole, 'user');

    expect($userWithAdminRole->teamRole($team))
        ->toBeInstanceOf(Role::class)
        ->key->toBe('admin')
        ->and($userWithUserRole->teamRole($team))
        ->toBeInstanceOf(Role::class)
        ->key->toBe('user');
});

it('checks whether user has team role', function () {
    $team = Team::factory()->create();

    $userWithAdminRole = User::create();
    $userWithUserRole = User::create();
    $userNotInTeam = User::create();

    $team->addMember($userWithAdminRole, 'admin');
    $team->addMember($userWithUserRole, 'user');

    expect($userWithAdminRole->hasTeamRole($team, 'admin'))
        ->toBeTrue()
        ->and($userWithAdminRole->hasTeamRole($team, 'user'))
        ->toBeFalse()
        ->and($userWithUserRole->hasTeamRole($team, 'user'))
        ->toBeTrue()
        ->and($userWithUserRole->hasTeamRole($team, 'admin'))
        ->toBeFalse()
        ->and($userNotInTeam->hasTeamRole($team, 'admin'))
        ->toBeFalse()
        ->and($userNotInTeam->hasTeamRole($team, 'user'))
        ->toBeFalse();
});

it('checks whether user has permission', function () {
    $team = Team::factory()->create();

    $userWithAdminRole = User::create();
    $userWithUserRole = User::create();
    $userNotInTeam = User::create();

    $team->addMember($userWithAdminRole, 'admin');
    $team->addMember($userWithUserRole, 'user');

    expect($userWithAdminRole->hasTeamPermission($team, '*'))
        ->toBeTrue()
        ->and($userWithUserRole->hasTeamPermission($team, '*'))
        ->toBeFalse()
        ->and($userNotInTeam->hasTeamPermission($team, '*'))
        ->toBeFalse();
});
