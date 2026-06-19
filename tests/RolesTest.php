<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Roles\Roles;

it('returns all registered roles', function () {
    // roles are registered in TestCase file

    expect(Roles::all())
        ->toBeArray()
        ->toHaveKeys(['admin', 'user'])
        ->and(Roles::all()['admin'])
        ->key->toBe('admin')
        ->name->toBe('Admin')
        ->permissions->toBe(['*']);
});

it('returns registered role', function () {
    $role = Roles::find('admin');

    expect($role)
        ->key->toBe('admin')
        ->name->toBe('Admin')
        ->permissions->toBe(['*']);
});

it('registers and returns role', function () {
    $role = Roles::register('unique', 'Unique role', ['one']);

    expect($role)
        ->toBeInstanceOf(Role::class)
        ->key->toBe('unique')
        ->name->toBe('Unique role')
        ->permissions->toBe(['one']);
});
