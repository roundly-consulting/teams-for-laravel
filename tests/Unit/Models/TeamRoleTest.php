<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\Roles\Role;

it('casts permissions to a collection', function (): void {
    $role = TeamRole::factory()->create(['permissions' => ['a', 'b']]);

    expect($role->permissions->all())->toBe(['a', 'b']);
});

it('belongs to a team', function (): void {
    expect((new TeamRole)->team())->toBeInstanceOf(BelongsTo::class);
});

it('converts to a Role value object', function (): void {
    $team = Team::factory()->create();
    $teamRole = TeamRole::factory()->for($team)->create([
        'key' => 'editor',
        'name' => 'Editor',
        'permissions' => ['posts.edit'],
        'description' => 'desc',
    ]);

    expect($teamRole->toRole())
        ->toBeInstanceOf(Role::class)
        ->key->toBe('editor')
        ->name->toBe('Editor')
        ->description->toBe('desc')
        ->permissions->toBe(['posts.edit']);
});

it('uses its factory', function (): void {
    expect(TeamRole::factory()->make())->toBeInstanceOf(TeamRole::class);
});
