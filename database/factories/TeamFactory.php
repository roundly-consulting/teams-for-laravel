<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Support\TeamModel;

/** @extends Factory<Team> */
final class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * Build the model the host configured in `teams.models.team`, not the packaged one, so a host
     * subclass comes out of the factory with its own events, observers and table.
     *
     * @return class-string<Team>
     */
    public function modelName(): string
    {
        return TeamModel::class();
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_public' => fake()->boolean(10),
            'meta' => [],
        ];
    }
}
