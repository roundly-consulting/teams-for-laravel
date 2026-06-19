<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Tests\User;

it('creates a team via the facade', function () {
    $team = Teams::createTeam(new CreateTeamData(name: 'Acme'));

    expect($team)->toBeInstanceOf(Team::class)->name->toBe('Acme');
});

it('exposes a fluent builder via for()', function () {
    $team = Team::factory()->create();
    $alice = User::create();
    $bob = User::create();

    Teams::for($team)
        ->addMember($alice, 'admin')
        ->addMember($bob, 'user');

    expect($team->members()->count())->toBe(2);
});

it('accepts an invite via the facade', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $invite = Invite::factory()->for($team)->create(['role' => 'admin']);

    $member = Teams::acceptInvite($invite, new AcceptInviteData(member: $user));

    expect($member->role)->toBe('admin');
});

it('resolves a role and the role map via the facade', function () {
    expect(Teams::role('admin'))->toBeInstanceOf(Role::class)
        ->and(Teams::role('nope'))->toBeNull()
        ->and(Teams::roles())->toHaveKeys(['admin', 'user']);
});
