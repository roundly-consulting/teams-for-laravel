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

it('replaces an existing key on re-register', function () {
    $provider = new InMemoryRoleProvider;

    $provider->register('admin', 'Admin', ['*']);
    $second = $provider->register('admin', 'Changed', ['none']);

    expect($provider->find('admin'))->toBe($second)->name->toBe('Changed');
});
