<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Roles\Permissions;
use RoundlyConsulting\Teams\Roles\Roles;

it('registers and finds a permission', function (): void {
    Permissions::register('posts.publish', 'Publish posts', 'Content');

    expect(Permissions::find('posts.publish'))
        ->toBeInstanceOf(Permission::class)
        ->name->toBe('Publish posts')
        ->group->toBe('Content');
});

it('returns all registered permissions', function (): void {
    Permissions::register('a', 'A');
    Permissions::register('b', 'B');

    expect(Permissions::all())->toHaveKeys(['a', 'b']);
});

it('is idempotent with last write winning', function (): void {
    Permissions::register('x', 'First');
    Permissions::register('x', 'Second', 'Group');

    expect(Permissions::find('x')->name)->toBe('Second')
        ->and(Permissions::find('x')->group)->toBe('Group')
        ->and(Permissions::all())->toHaveCount(1);
});

it('groups permissions and preserves an existing name', function (): void {
    Permissions::register('posts.edit', 'Edit posts');
    Permissions::group('Content', ['posts.edit', 'posts.delete']);

    expect(Permissions::find('posts.edit')->group)->toBe('Content')
        ->and(Permissions::find('posts.edit')->name)->toBe('Edit posts')
        ->and(Permissions::find('posts.delete')->group)->toBe('Content');
});

it('groups permission value objects', function (): void {
    Permissions::group('Billing', [new Permission('billing.view', 'View billing')]);

    expect(Permissions::find('billing.view'))
        ->name->toBe('View billing')
        ->group->toBe('Billing');
});

it('harvests permissions from registered roles', function (): void {
    Roles::register('editor', 'Editor', ['posts.edit', 'posts.publish']);
    Permissions::register('teams.manage', 'Manage');

    $harvested = Permissions::fromRoles();

    expect($harvested)->toHaveKeys(['teams.manage', 'posts.edit', 'posts.publish']);
});

it('passes through the Teams facade', function (): void {
    Permissions::register('posts.publish', 'Publish');

    expect(Teams::permissions())->toHaveKey('posts.publish');
});
