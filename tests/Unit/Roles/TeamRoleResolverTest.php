<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Roles\TeamRoleResolver;

beforeEach(function (): void {
    Teams::roles()->register('editor', 'Editor', ['posts.edit']);
});

it('falls back to the global role when no override exists', function (): void {
    config()->set('teams.roles.per_team', true);

    $team = Team::factory()->create();

    expect(app(TeamRoleResolver::class)->resolve($team, 'editor'))
        ->toBeInstanceOf(Role::class)
        ->permissions->toBe(['posts.edit']);
});

it('lets a team override win over the global role', function (): void {
    config()->set('teams.roles.per_team', true);

    $team = Team::factory()->create();
    $team->defineRole('editor', 'Editor', ['posts.edit', 'posts.publish']);

    expect(app(TeamRoleResolver::class)->resolve($team, 'editor')->permissions)
        ->toBe(['posts.edit', 'posts.publish']);
});

it('returns null for an unknown key', function (): void {
    config()->set('teams.roles.per_team', true);

    $team = Team::factory()->create();

    expect(app(TeamRoleResolver::class)->resolve($team, 'ghost'))->toBeNull();
});

it('short-circuits to the global provider with no override query when the flag is off', function (): void {
    config()->set('teams.roles.per_team', false);

    $team = Team::factory()->create();
    $team->defineRole('editor', 'Editor', ['posts.publish']);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $role = app(TeamRoleResolver::class)->resolve($team, 'editor');

    expect($role->permissions)->toBe(['posts.edit']);
    expect(collect($queries)->filter(fn (string $sql): bool => str_contains($sql, 'team_role_overrides')))
        ->toBeEmpty();
});

it('merges global roles with team overrides in all()', function (): void {
    config()->set('teams.roles.per_team', true);

    $team = Team::factory()->create();
    $team->defineRole('editor', 'Editor', ['posts.publish']);

    $roles = app(TeamRoleResolver::class)->all($team);

    expect($roles)->toHaveKey('editor')
        ->and($roles['editor']->permissions)->toBe(['posts.publish'])
        ->and($roles)->toHaveKey('admin');
});

it('returns only global roles from all() when the flag is off', function (): void {
    config()->set('teams.roles.per_team', false);

    $team = Team::factory()->create();
    $team->defineRole('editor', 'Editor', ['posts.publish']);

    $roles = app(TeamRoleResolver::class)->all($team);

    expect($roles['editor']->permissions)->toBe(['posts.edit']);
});
