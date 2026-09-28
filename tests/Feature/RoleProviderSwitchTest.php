<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\RoleDefinition;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;
use RoundlyConsulting\Teams\Roles\DatabaseRoleProvider;
use RoundlyConsulting\Teams\Roles\InMemoryRoleProvider;

it('binds the array provider by default', function () {
    expect(app(RoleProvider::class))->toBeInstanceOf(InMemoryRoleProvider::class);
});

it('binds the database provider when configured', function () {
    config()->set('teams.roles.provider', 'database');
    app()->forgetInstance(RoleProvider::class);

    expect(app(RoleProvider::class))->toBeInstanceOf(DatabaseRoleProvider::class);

    Teams::roles()->register('manager', 'Manager', ['manage']);

    $this->assertDatabaseHas('team_roles', ['key' => 'manager']);

    expect(Teams::roles()->find('manager'))->name->toBe('Manager')
        ->and(RoleDefinition::query()->where('key', 'manager')->exists())->toBeTrue();
});
