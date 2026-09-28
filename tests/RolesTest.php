<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Roles\Role;

it('returns all registered roles', function () {
    // roles are registered in TestCase file

    expect(Teams::roles()->all())
        ->toBeArray()
        ->toHaveKeys(['admin', 'user'])
        ->and(Teams::roles()->all()['admin'])
        ->key->toBe('admin')
        ->name->toBe('Admin')
        ->permissions->toBe(['*']);
});

it('returns registered role', function () {
    $role = Teams::roles()->find('admin');

    expect($role)
        ->key->toBe('admin')
        ->name->toBe('Admin')
        ->permissions->toBe(['*']);
});

it('registers and returns role', function () {
    $role = Teams::roles()->register('unique', 'Unique role', ['one']);

    expect($role)
        ->toBeInstanceOf(Role::class)
        ->key->toBe('unique')
        ->name->toBe('Unique role')
        ->permissions->toBe(['one']);
});
