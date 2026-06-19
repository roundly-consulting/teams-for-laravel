<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Models\RoleDefinition;
use RoundlyConsulting\Teams\Roles\DatabaseRoleProvider;
use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Roles\Role;

it('registers a role by upserting a definition', function () {
    $provider = new DatabaseRoleProvider;

    $role = $provider->register('editor', 'Editor', ['edit', new Permission('publish')]);

    expect($role)->toBeInstanceOf(Role::class)->key->toBe('editor')
        ->and($role->permissions)->toBe(['edit', 'publish']);

    $this->assertDatabaseHas('team_roles', ['key' => 'editor', 'name' => 'Editor']);
});

it('updates an existing definition on re-register', function () {
    $provider = new DatabaseRoleProvider;

    $provider->register('admin', 'Admin', ['*']);
    $provider->register('admin', 'Administrator', ['manage']);

    expect(RoleDefinition::query()->where('key', 'admin')->count())->toBe(1)
        ->and($provider->find('admin'))->name->toBe('Administrator')
        ->and($provider->find('admin')->permissions)->toBe(['manage']);
});

it('reads roles persisted in the database', function () {
    RoleDefinition::factory()->create([
        'key' => 'viewer',
        'name' => 'Viewer',
        'permissions' => ['read'],
        'description' => 'Can read',
    ]);

    $provider = new DatabaseRoleProvider;

    expect($provider->find('viewer'))
        ->key->toBe('viewer')
        ->description->toBe('Can read')
        ->and($provider->all())->toHaveKey('viewer')
        ->and($provider->find('absent'))->toBeNull();
});
