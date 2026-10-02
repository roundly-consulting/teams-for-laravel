<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Actions\DefineTeamRoleAction;
use RoundlyConsulting\Teams\DataTransferObjects\DefineTeamRoleData;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\Roles\Permission;

it('creates a team role override', function (): void {
    $team = Team::factory()->create();

    $role = app(DefineTeamRoleAction::class)->execute(new DefineTeamRoleData(
        teamId: (int) $team->getKey(),
        key: 'editor',
        name: 'Editor',
        permissions: ['posts.edit', new Permission('posts.publish')],
        description: 'Team editor',
    ));

    expect($role)->toBeInstanceOf(TeamRole::class)
        ->and($role->permissions->all())->toBe(['posts.edit', 'posts.publish'])
        ->and($role->description)->toBe('Team editor');
});

it('upserts without creating a duplicate', function (): void {
    $team = Team::factory()->create();

    $action = app(DefineTeamRoleAction::class);

    $action->execute(new DefineTeamRoleData((int) $team->getKey(), 'editor', 'Editor', ['posts.edit']));
    $action->execute(new DefineTeamRoleData((int) $team->getKey(), 'editor', 'Editor', ['posts.publish']));

    expect(TeamRole::query()->where('team_id', $team->getKey())->where('key', 'editor')->count())->toBe(1)
        ->and($team->teamRoles()->where('key', 'editor')->first()->permissions->all())->toBe(['posts.publish']);
});

it('redefines a per-team role whose override was soft-deleted', function (): void {
    $team = Team::factory()->create();
    Teams::for($team)->roles()->define('editor', 'Editor', ['posts.edit'])->delete();

    $again = Teams::for($team)->roles()->define('editor', 'Chief editor', ['posts.publish'], 'Runs the desk');

    expect($again->trashed())->toBeFalse()
        ->and($again->name)->toBe('Chief editor')
        ->and($again->permissions->all())->toBe(['posts.publish'])
        ->and($again->description)->toBe('Runs the desk')
        ->and(TeamRole::withTrashed()->where('team_id', $team->getKey())->count())->toBe(1);
});
