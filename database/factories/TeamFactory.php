<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Teams\Models\Team;

/** @extends Factory<Team> */
final class TeamFactory extends Factory
{
    protected $model = Team::class;

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
