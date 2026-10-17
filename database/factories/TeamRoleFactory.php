<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\Support\TeamRoleModel;

/** @extends Factory<TeamRole> */
final class TeamRoleFactory extends Factory
{
    protected $model = TeamRole::class;

    /**
     * Build the model the host configured in `teams.models.team_role`, not the packaged one, so a
     * host subclass comes out of the factory with its own events, observers and table.
     *
     * @return class-string<TeamRole>
     */
    public function modelName(): string
    {
        return TeamRoleModel::class();
    }

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
