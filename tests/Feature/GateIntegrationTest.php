<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Roles;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(function () {
    Roles::register('manager', 'Manager', ['manage-billing']);
});

it('allows a permitted ability through the gate', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'manager');

    expect(Gate::forUser($user)->allows('teams.manage-billing', $team))->toBeTrue();
});

it('denies a missing permission through the gate', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'manager');

    expect(Gate::forUser($user)->allows('teams.delete-everything', $team))->toBeFalse();
});

it('ignores abilities outside the configured prefix', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'manager');

    expect(Gate::forUser($user)->allows('unrelated.ability', $team))->toBeFalse();
});

it('ignores prefixed abilities when no team is passed', function () {
    $user = User::create();

    expect(Gate::forUser($user)->allows('teams.manage-billing'))->toBeFalse();
});
