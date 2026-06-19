<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Roles\Role;

it('holds a key and an optional name', function () {
    $permission = new Permission('manage-billing', 'Manage billing');

    expect($permission)
        ->key->toBe('manage-billing')
        ->name->toBe('Manage billing');
});

it('defaults the name to an empty string', function () {
    expect((new Permission('invite'))->name)->toBe('');
});

it('normalises permission objects into keys on a role', function () {
    $role = new Role('admin', 'Admin', [new Permission('a'), new Permission('b'), 'c']);

    expect($role->permissions)->toBe(['a', 'b', 'c'])
        ->and($role->permissionObjects)->toHaveCount(3)
        ->and($role->hasPermission('b'))->toBeTrue();
});

it('treats a wildcard permission as granting everything', function () {
    $role = new Role('admin', 'Admin', ['*']);

    expect($role)
        ->hasPermission('anything')->toBeTrue()
        ->hasPermission('')->toBeFalse();
});
