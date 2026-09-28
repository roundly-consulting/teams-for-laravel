<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Roles\Contracts\RoleProvider;

it('lists registered roles', function () {
    Teams::roles()->register('editor', 'Editor', ['edit', 'publish']);

    $exitCode = Artisan::call('teams:roles');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('editor')
        ->toContain('Editor')
        ->toContain('edit, publish');
});

it('reports when no roles are registered', function () {
    config()->set('teams.roles.provider', 'database');
    app()->forgetInstance(RoleProvider::class);

    $exitCode = Artisan::call('teams:roles');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('No roles are registered.');
});
