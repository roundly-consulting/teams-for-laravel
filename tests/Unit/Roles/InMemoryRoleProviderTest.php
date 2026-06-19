<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Roles\InMemoryRoleProvider;
use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Roles\Role;

it('registers, finds and lists roles', function () {
    $provider = new InMemoryRoleProvider;

    $role = $provider->register('editor', 'Editor', ['edit', new Permission('publish')]);

    expect($role)->toBeInstanceOf(Role::class)->key->toBe('editor')
        ->and($role->permissions)->toBe(['edit', 'publish'])
        ->and($provider->find('editor'))->toBe($role)
        ->and($provider->find('missing'))->toBeNull()
        ->and($provider->all())->toHaveKey('editor');
});

it('does not re-register an existing key', function () {
    $provider = new InMemoryRoleProvider;

    $first = $provider->register('admin', 'Admin', ['*']);
    $second = $provider->register('admin', 'Changed', ['none']);

    expect($second)->toBe($first)->name->toBe('Admin');
});
