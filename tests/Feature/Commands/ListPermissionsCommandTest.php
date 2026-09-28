<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Facades\Teams;

it('lists registered permissions', function (): void {
    Teams::permissions()->register('posts.publish', 'Publish posts', 'Content');

    $this->artisan('teams:permissions')
        ->expectsOutputToContain('posts.publish')
        ->assertSuccessful();

    expect(Teams::permissions()->find('posts.publish')->group)->toBe('Content');
});

it('reports when no permissions are registered', function (): void {
    $this->artisan('teams:permissions')
        ->expectsOutput('No permissions are registered.')
        ->assertSuccessful();
});
