<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(function (): void {
    config()->set('teams.roles.per_team', true);
    Teams::roles()->register('editor', 'Editor', ['posts.edit']);
});

it('gives a team-specific permission only to that team', function (): void {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    $teamA->defineRole('editor', 'Editor', ['posts.edit', 'posts.publish']);

    $alice = User::create();
    $bob = User::create();

    $teamA->addMember($alice, 'editor');
    $teamB->addMember($bob, 'editor');

    expect($alice->hasTeamPermission($teamA, 'posts.publish'))->toBeTrue()
        ->and($bob->hasTeamPermission($teamB, 'posts.publish'))->toBeFalse()
        ->and($bob->hasTeamPermission($teamB, 'posts.edit'))->toBeTrue();
});

it('authorizes through the gate per team', function (): void {
    $teamA = Team::factory()->create();
    $teamA->defineRole('editor', 'Editor', ['posts.publish']);

    $alice = User::create();
    $teamA->addMember($alice, 'editor');

    expect($alice->can('teams.posts.publish', $teamA))->toBeTrue();
});

it('exposes the merged role map via Team::roles()', function (): void {
    $team = Team::factory()->create();
    $team->defineRole('editor', 'Editor', ['posts.publish']);

    $roles = $team->roles();

    expect($roles['editor']->permissions)->toBe(['posts.publish'])
        ->and($roles)->toHaveKey('admin');
});

it('upserts overrides idempotently via defineRole', function (): void {
    $team = Team::factory()->create();

    $team->defineRole('editor', 'Editor', ['posts.edit']);
    $team->defineRole('editor', 'Editor', ['posts.publish']);

    expect($team->teamRoles()->where('key', 'editor')->count())->toBe(1);
});
