<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Roles\Permissions;

it('lists registered permissions', function (): void {
    Permissions::register('posts.publish', 'Publish posts', 'Content');

    $this->artisan('teams:permissions')
        ->expectsOutputToContain('posts.publish')
        ->assertSuccessful();

    expect(Permissions::find('posts.publish')->group)->toBe('Content');
});

it('reports when no permissions are registered', function (): void {
    $this->artisan('teams:permissions')
        ->expectsOutput('No permissions are registered.')
        ->assertSuccessful();
});
