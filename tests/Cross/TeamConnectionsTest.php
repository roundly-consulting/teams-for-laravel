<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('connects two teams and lists partners of a type', function (): void {
    $team = Team::factory()->create();
    $partner = Team::factory()->create();

    $team->connectTo($partner, ['share:roster']);

    expect($team->isConnectedTo($partner))->toBeTrue()
        ->and($team->connectablesOfType(Team::class)->pluck('id')->all())->toBe([$partner->getKey()]);
});

it('disconnects a team-to-team connection', function (): void {
    $team = Team::factory()->create();
    $partner = Team::factory()->create();

    $team->connectTo($partner);
    expect($team->isConnectedTo($partner))->toBeTrue();

    $team->disconnectFrom($partner);
    expect($team->isConnectedTo($partner))->toBeFalse();
});

it('toggles a team-to-team connection on and off', function (): void {
    $team = Team::factory()->create();
    $partner = Team::factory()->create();

    $team->toggleConnection($partner);
    expect($team->isConnectedTo($partner))->toBeTrue();

    $team->toggleConnection($partner);
    expect($team->isConnectedTo($partner))->toBeFalse();
});

it('invites and accepts a partner connection', function (): void {
    $team = Team::factory()->create();
    $partner = Team::factory()->create();

    $team->inviteConnection($partner);
    expect($team->isConnectedTo($partner))->toBeFalse();

    $partner->acceptConnectionFrom($team);
    expect($team->isConnectedTo($partner))->toBeTrue();
});

it('carries permissions through a connection', function (): void {
    $team = Team::factory()->create();
    $partner = Team::factory()->create();

    $team->connectTo($partner, ['share:roster']);

    expect($team->hasPermissionThroughConnection($partner, 'share:roster'))->toBeTrue();

    $team->grantThroughConnection($partner, 'share:billing');
    expect($team->hasPermissionThroughConnection($partner, 'share:billing'))->toBeTrue();

    $team->revokeThroughConnection($partner, 'share:billing');
    expect($team->hasPermissionThroughConnection($partner, 'share:billing'))->toBeFalse();
});

it('connects a team to a user', function (): void {
    $team = Team::factory()->create();
    $user = User::create();

    $team->connectTo($user, ['delegate:admin']);

    expect($team->isConnectedTo($user))->toBeTrue()
        ->and($team->connectablesOfType(User::class)->pluck('id')->all())->toBe([$user->getKey()])
        ->and($team->hasPermissionThroughConnection($user, 'delegate:admin'))->toBeTrue();
});

it('delegates to the connections package through the team handle', function (): void {
    $team = Team::factory()->create();
    $partner = Team::factory()->create();

    Teams::for($team)->connections()->to($partner)->withPermissions('share:roster')->connect();
    expect($team->isConnectedTo($partner))->toBeTrue()
        ->and($team->hasPermissionThroughConnection($partner, 'share:roster'))->toBeTrue();

    Teams::for($team)->connections()->to($partner)->disconnect();
    expect($team->isConnectedTo($partner))->toBeFalse();
});
