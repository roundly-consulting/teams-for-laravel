<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Models\RoleDefinition;
use RoundlyConsulting\Teams\Roles\CachedRoleProvider;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Roles\DatabaseRoleProvider;
use RoundlyConsulting\Teams\Roles\InMemoryRoleProvider;

dataset('role providers', [
    'array' => fn (): RoleProvider => new InMemoryRoleProvider,
    'database' => fn (): RoleProvider => new DatabaseRoleProvider,
    'cached database' => fn (): RoleProvider => new CachedRoleProvider(new DatabaseRoleProvider(memoize: false)),
]);

it('replaces a role on re-register, whichever provider backs it', function (RoleProvider $provider): void {
    $provider->register('admin', 'Admin', ['*']);
    $provider->register('admin', 'Administrator', ['billing']);

    expect($provider->find('admin')?->name)->toBe('Administrator')
        ->and($provider->find('admin')?->permissions)->toBe(['billing'])
        ->and($provider->find('admin')?->hasPermission('members.manage'))->toBeFalse()
        ->and($provider->all())->toHaveCount(1);
})->with('role providers');

it('keeps the description a role is registered with', function (RoleProvider $provider): void {
    $returned = $provider->register('editor', 'Editor', ['posts.edit'], description: 'Can edit content.');

    expect($returned->description)->toBe('Can edit content.')
        ->and($provider->find('editor')?->description)->toBe('Can edit content.');
})->with('role providers');

it('persists the description in the roles table', function (): void {
    (new DatabaseRoleProvider)->register('editor', 'Editor', ['posts.edit'], description: 'Can edit content.');

    expect(RoleDefinition::query()->where('key', 'editor')->value('description'))->toBe('Can edit content.')
        ->and((new DatabaseRoleProvider)->find('editor')?->description)->toBe('Can edit content.');
});
