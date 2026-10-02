<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Roles\Role;

it('holds values in public properties', function () {
    $role = new Role('admin', 'Admin', ['everything'], 'Most powerful role');

    expect($role)
        ->key->toBe('admin')
        ->name->toBe('Admin')
        ->permissions->toBe(['everything'])
        ->description->toBe('Most powerful role');
});

it('checks whether role has permission', function () {
    $role = new Role('admin', 'Admin', ['everything'], 'Most powerful role');

    expect($role)
        ->hasPermission('everything')->toBeTrue()
        ->hasPermission('nothing')->toBeFalse();
});

it('checks whether role has any permission', function () {
    $role = new Role('admin', 'Admin', ['everything'], 'Most powerful role');

    expect($role)
        ->hasAnyPermission(['everything'])->toBeTrue()
        ->hasAnyPermission(['everything', 'something'])->toBeTrue()
        ->hasAnyPermission(['nothing'])->toBeFalse();
});

it('checks whether role has all permissions', function () {
    $role = new Role('admin', 'Admin', ['everything'], 'Most powerful role');

    expect($role)
        ->hasAllPermissions(['everything'])->toBeTrue()
        ->hasAllPermissions(['everything', 'something'])->toBeFalse()
        ->hasAllPermissions(['nothing'])->toBeFalse();
});
