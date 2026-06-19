<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\Models\RoleDefinition;

it('casts permissions to a collection', function () {
    $definition = RoleDefinition::factory()->create([
        'key' => 'viewer',
        'permissions' => ['read', 'list'],
    ]);

    expect($definition->permissions->all())->toBe(['read', 'list']);
});
