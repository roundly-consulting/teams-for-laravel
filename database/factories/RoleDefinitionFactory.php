<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Teams\Models\RoleDefinition;

/** @extends Factory<RoleDefinition> */
final class RoleDefinitionFactory extends Factory
{
    protected $model = RoleDefinition::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(1),
            'name' => fake()->words(2, true),
            'permissions' => [],
            'description' => '',
        ];
    }
}
