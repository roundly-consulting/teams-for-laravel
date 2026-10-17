<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Teams\Enums\JoinRequestStatus;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Support\JoinRequestModel;

/** @extends Factory<JoinRequest> */
final class JoinRequestFactory extends Factory
{
    protected $model = JoinRequest::class;

    /**
     * Build the model the host configured in `teams.models.join_request`, not the packaged one, so
     * a host subclass comes out of the factory with its own events, observers and table.
     *
     * @return class-string<JoinRequest>
     */
    public function modelName(): string
    {
        return JoinRequestModel::class();
    }

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
            'expires_at' => null,
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function expiringWithin(int $days): self
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->addDays($days),
        ]);
    }
}
