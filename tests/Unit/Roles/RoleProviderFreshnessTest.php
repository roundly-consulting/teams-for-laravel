<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\RoleDefinition;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\CachedRoleProvider;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Roles\DatabaseRoleProvider;
use RoundlyConsulting\Teams\Tests\User;

/**
 * Another process (an admin request, another worker) rewrites a role's permissions —
 * a plain write, so no model event fires in THIS process.
 *
 * @param  list<string>  $permissions
 */
function revokeElsewhere(string $key, array $permissions): void
{
    RoleDefinition::query()->where('key', $key)->update(['permissions' => json_encode($permissions)]);
}

function useDatabaseRoles(bool $cached = false): void
{
    config()->set('teams.roles.provider', 'database');
    config()->set('teams.roles.cache.enabled', $cached);
    app()->forgetInstance(RoleProvider::class);
}

it('drops a permission revoked elsewhere at the next job or request', function (): void {
    useDatabaseRoles();
    Teams::roles()->register('editor', 'Editor', ['posts.publish']);
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'editor');

    expect($team->memberHasPermission($user, 'posts.publish'))->toBeTrue();

    revokeElsewhere('editor', []);

    // What the queue worker does before every job, and Octane before every request.
    app()->forgetScopedInstances();

    expect($team->memberHasPermission($user, 'posts.publish'))->toBeFalse();
});

it('never lets a stale worker refill the shared cache with a revoked permission', function (): void {
    useDatabaseRoles(cached: true);
    $worker = app(RoleProvider::class);
    $worker->register('editor', 'Editor', ['posts.publish']);
    $worker->all();

    // The admin's request revokes the permission and flushes the shared cache.
    (new CachedRoleProvider(new DatabaseRoleProvider))->register('editor', 'Editor', []);

    // The worker keeps checking permissions in the same job…
    $worker->all();

    // …and the next request must read the revocation, not the worker's old map.
    app()->forgetScopedInstances();

    expect(app(RoleProvider::class)->find('editor')?->permissions)->toBe([]);
});

it('invalidates the role map when a definition is changed through the model', function (bool $cached): void {
    useDatabaseRoles($cached);
    Teams::roles()->register('editor', 'Editor', ['posts.publish']);
    $team = Team::factory()->create();
    $user = User::create();
    $team->addMember($user, 'editor');

    expect($team->memberHasPermission($user, 'posts.publish'))->toBeTrue();

    RoleDefinition::query()->where('key', 'editor')->firstOrFail()->update(['permissions' => []]);

    expect($team->memberHasPermission($user, 'posts.publish'))->toBeFalse();

    RoleDefinition::query()->where('key', 'editor')->firstOrFail()->delete();

    expect(Teams::roles()->find('editor'))->toBeNull();
})->with(['uncached' => false, 'cached' => true]);

it('keeps roles registered at boot across jobs with the array provider', function (): void {
    Teams::roles()->register('editor', 'Editor', ['posts.publish']);

    app()->forgetScopedInstances();

    expect(Teams::roles()->find('editor')?->permissions)->toBe(['posts.publish'])
        ->and(Teams::roles()->find('admin'))->not->toBeNull();
});
