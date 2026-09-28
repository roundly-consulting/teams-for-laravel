<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(function () {
    Teams::roles()->register('manager', 'Manager', ['manage-billing']);
});

it('renders the protected block when the member has the permission', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'manager');

    $output = Blade::render(
        '@teamPermission($team, "manage-billing", $user) visible @endteamPermission',
        ['team' => $team, 'user' => $user],
    );

    expect(trim($output))->toBe('visible');
});

it('hides the protected block when the member lacks the permission', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'user');

    $output = Blade::render(
        '@teamPermission($team, "manage-billing", $user) visible @endteamPermission',
        ['team' => $team, 'user' => $user],
    );

    expect(trim($output))->toBe('');
});

it('renders by role with the team role directive', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'manager');

    $output = Blade::render(
        '@teamRole($team, "manager", $user) role-ok @endteamRole',
        ['team' => $team, 'user' => $user],
    );

    expect(trim($output))->toBe('role-ok');
});

it('falls back to the authenticated user when no member is given', function () {
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'manager');

    auth()->setUser($user);

    $output = Blade::render(
        '@teamPermission($team, "manage-billing") auth-visible @endteamPermission',
        ['team' => $team],
    );

    expect(trim($output))->toBe('auth-visible');
});
