<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Teams\Enums\JoinPolicy;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Exceptions\TeamsException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Options\DefaultMemberRole;
use RoundlyConsulting\Teams\Options\MaxSeats;
use RoundlyConsulting\Teams\Options\RequireApprovalToJoin;
use RoundlyConsulting\Teams\Tests\User;

it('reads each option default and persists a set value scoped to the team', function (): void {
    $team = Team::factory()->create();

    expect($team->option(MaxSeats::class)->value())->toBeNull()
        ->and($team->option(DefaultMemberRole::class)->value())->toBeNull()
        ->and($team->option(RequireApprovalToJoin::class)->value())->toBeFalse()
        ->and(Options::get(RoundlyConsulting\Teams\Options\JoinPolicy::class, $team))->toBe(JoinPolicy::Request);

    $team->option(MaxSeats::class)->set(25);

    expect(Options::get(MaxSeats::class, $team))->toBe(25);
});

it('exposes typed settings sugar through the builder', function (): void {
    $team = Team::factory()->create();

    Teams::for($team)->settings()
        ->setJoinPolicy(JoinPolicy::Open)
        ->setMaxSeats(3)
        ->setDefaultMemberRole('admin')
        ->setRequireApprovalToJoin(true);

    $settings = Teams::for($team)->settings();

    expect($settings->joinPolicy())->toBe(JoinPolicy::Open)
        ->and($settings->maxSeats())->toBe(3)
        ->and($settings->defaultMemberRole())->toBe('admin')
        ->and($settings->requireApprovalToJoin())->toBeTrue();
});

it('reverts max seats to unlimited when set to null', function (): void {
    $team = Team::factory()->create();

    Teams::for($team)->settings()->setMaxSeats(5)->setMaxSeats(null);

    expect(Teams::for($team)->settings()->maxSeats())->toBeNull();
});

it('allows adding a member up to the seat cap', function (): void {
    $team = Team::factory()->create();
    Teams::for($team)->settings()->setMaxSeats(2);

    $team->addMember(User::create(), 'user');
    $team->addMember(User::create(), 'user');

    expect($team->members()->count())->toBe(2);
});

it('blocks adding a member past the seat cap', function (): void {
    $team = Team::factory()->create();
    Teams::for($team)->settings()->setMaxSeats(1);

    $team->addMember(User::create(), 'user');

    expect(fn () => $team->addMember(User::create(), 'user'))
        ->toThrow(TeamsException::class, 'seat limit');
});

it('does not count an idempotent re-add against the seat cap', function (): void {
    $team = Team::factory()->create();
    $member = User::create();
    $team->addMember($member, 'user');

    Teams::for($team)->settings()->setMaxSeats(1);

    // Re-adding the same member changes the role but never consumes a new seat.
    $team->addMember($member, 'admin');

    expect($team->findMember($member)?->role)->toBe('admin');
});

it('rejects a join request on an invite-only team', function (): void {
    $team = Team::factory()->create();
    Teams::for($team)->settings()->setJoinPolicy(JoinPolicy::InviteOnly);

    expect(fn () => Teams::requestToJoin($team, User::create()))
        ->toThrow(TeamsException::class, 'invite-only');
});

it('leaves a join request pending under the request policy', function (): void {
    $team = Team::factory()->create();

    $request = Teams::requestToJoin($team, User::create());

    expect($request->status)->toBe(JoinRequestStatus::Pending)
        ->and($team->members()->count())->toBe(0);
});

it('auto-approves a join request on an open team', function (): void {
    $team = Team::factory()->create();
    Teams::for($team)->settings()->setJoinPolicy(JoinPolicy::Open);
    $user = User::create();

    $request = Teams::requestToJoin($team, $user);

    expect($request->status)->toBe(JoinRequestStatus::Approved)
        ->and($team->hasMember($user))->toBeTrue();
});

it('keeps an open-team request pending when approval is required', function (): void {
    $team = Team::factory()->create();
    Teams::for($team)->settings()
        ->setJoinPolicy(JoinPolicy::Open)
        ->setRequireApprovalToJoin(true);
    $user = User::create();

    $request = Teams::requestToJoin($team, $user);

    expect($request->status)->toBe(JoinRequestStatus::Pending)
        ->and($team->hasMember($user))->toBeFalse();
});

it('approves a join request with the team default member role', function (): void {
    $team = Team::factory()->create();
    Teams::for($team)->settings()->setDefaultMemberRole('admin');
    $user = User::create();
    $request = Teams::requestToJoin($team, $user);

    $member = Teams::for($team)->approveJoinRequest($request, User::create());

    expect($member->role)->toBe('admin');
});

it('falls back to the config default role when no team default is set', function (): void {
    config()->set('teams.roles.default', 'user');
    $team = Team::factory()->create();
    $user = User::create();
    $request = Teams::requestToJoin($team, $user);

    $member = Teams::for($team)->approveJoinRequest($request, User::create());

    expect($member->role)->toBe('user');
});
