<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\TeamBuilder;
use RoundlyConsulting\Teams\Tests\User;

it('adds, changes role and removes members fluently', function () {
    $team = Team::factory()->create();
    $user = User::create();

    $builder = new TeamBuilder($team);

    $result = $builder
        ->addMember($user, 'user')
        ->changeRole($user, 'admin');

    expect($result)->toBeInstanceOf(TeamBuilder::class)
        ->and($team->memberHasRole($user, 'admin'))->toBeTrue()
        ->and($builder->team())->toBe($team);

    $builder->removeMember($user);

    expect($team->hasMember($user))->toBeFalse();
});

it('ignores changeRole for a non-member', function () {
    $team = Team::factory()->create();
    $user = User::create();

    (new TeamBuilder($team))->changeRole($user, 'admin');

    expect($team->hasMember($user))->toBeFalse();
});

it('creates an invite fluently', function () {
    $team = Team::factory()->create();
    $inviter = User::create();

    $invite = (new TeamBuilder($team))->invite(
        role: 'admin',
        email: 'jane@acme.test',
        invitedBy: $inviter,
    );

    expect($invite)->toBeInstanceOf(Invite::class)->email->toBe('jane@acme.test');
});

it('transfers ownership fluently', function () {
    $team = Team::factory()->create();
    $owner = User::create();

    (new TeamBuilder($team))->transferOwnershipTo($owner);

    expect($team->fresh()->isOwnedBy($owner))->toBeTrue();
});

it('defines a per-team role override fluently', function () {
    $team = Team::factory()->create();

    $role = (new TeamBuilder($team))->defineRole('editor', 'Editor', ['posts.publish'], 'Editors');

    expect($role)->toBeInstanceOf(TeamRole::class)
        ->and($role->permissions->all())->toBe(['posts.publish'])
        ->and($role->description)->toBe('Editors');
});

it('approves a join request fluently', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $admin = User::create();
    $request = JoinRequest::factory()->for($team)->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->getKey(),
        'requested_role' => 'admin',
        'status' => JoinRequestStatus::Pending,
    ]);

    $member = (new TeamBuilder($team))->approveJoinRequest($request, $admin);

    expect($member->role)->toBe('admin')
        ->and($request->fresh()->status)->toBe(JoinRequestStatus::Approved);
});

it('denies a join request fluently', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $admin = User::create();
    $request = JoinRequest::factory()->for($team)->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->getKey(),
        'status' => JoinRequestStatus::Pending,
    ]);

    $result = (new TeamBuilder($team))->denyJoinRequest($request, $admin);

    expect($result->status)->toBe(JoinRequestStatus::Denied);
});
