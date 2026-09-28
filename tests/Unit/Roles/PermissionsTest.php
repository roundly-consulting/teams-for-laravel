<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Roles\Permission;

it('registers and finds a permission', function (): void {
    Teams::permissions()->register('posts.publish', 'Publish posts', 'Content');

    expect(Teams::permissions()->find('posts.publish'))
        ->toBeInstanceOf(Permission::class)
        ->name->toBe('Publish posts')
        ->group->toBe('Content');
});

it('returns all registered permissions', function (): void {
    Teams::permissions()->register('a', 'A');
    Teams::permissions()->register('b', 'B');

    expect(Teams::permissions()->all())->toHaveKeys(['a', 'b']);
});

it('is idempotent with last write winning', function (): void {
    Teams::permissions()->register('x', 'First');
    Teams::permissions()->register('x', 'Second', 'Group');

    expect(Teams::permissions()->find('x')->name)->toBe('Second')
        ->and(Teams::permissions()->find('x')->group)->toBe('Group')
        ->and(Teams::permissions()->all())->toHaveCount(1);
});

it('groups permissions and preserves an existing name', function (): void {
    Teams::permissions()->register('posts.edit', 'Edit posts');
    Teams::permissions()->group('Content', ['posts.edit', 'posts.delete']);

    expect(Teams::permissions()->find('posts.edit')->group)->toBe('Content')
        ->and(Teams::permissions()->find('posts.edit')->name)->toBe('Edit posts')
        ->and(Teams::permissions()->find('posts.delete')->group)->toBe('Content');
});

it('groups permission value objects', function (): void {
    Teams::permissions()->group('Billing', [new Permission('billing.view', 'View billing')]);

    expect(Teams::permissions()->find('billing.view'))
        ->name->toBe('View billing')
        ->group->toBe('Billing');
});

it('harvests permissions from registered roles', function (): void {
    Teams::roles()->register('editor', 'Editor', ['posts.edit', 'posts.publish']);
    Teams::permissions()->register('teams.manage', 'Manage');

    $harvested = Teams::permissions()->fromRoles();

    expect($harvested)->toHaveKeys(['teams.manage', 'posts.edit', 'posts.publish']);
});

it('passes through the Teams facade', function (): void {
    Teams::permissions()->register('posts.publish', 'Publish');

    expect(Teams::permissions()->all())->toHaveKey('posts.publish');
});
