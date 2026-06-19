<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;

/** @extends Factory<TeamRole> */
final class TeamRoleFactory extends Factory
{
    protected $model = TeamRole::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'key' => fake()->unique()->slug(1),
            'name' => fake()->words(2, true),
            'permissions' => [],
            'description' => '',
        ];
    }
}
