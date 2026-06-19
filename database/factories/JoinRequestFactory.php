<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Team;

/** @extends Factory<JoinRequest> */
final class JoinRequestFactory extends Factory
{
    protected $model = JoinRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'requester_type' => 'user',
            'requester_id' => fake()->numberBetween(1, 1000),
            'requested_role' => null,
            'status' => JoinRequestStatus::Pending,
            'message' => null,
            'meta' => [],
        ];
    }
}
