<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Database\Factories;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Support\MemberModel;

/** @extends Factory<Member> */
final class MemberFactory extends Factory
{
    protected $model = Member::class;

    /**
     * Build the model the host configured in `teams.models.member`, not the packaged one, so a
     * host subclass comes out of the factory with its own events, observers and table.
     *
     * @return class-string<Member>
     */
    public function modelName(): string
    {
        return MemberModel::class();
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'member_type' => 'user',
            'member_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'role' => 'user',
            'accepted_invite_id' => null,
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

    public function expiringAt(CarbonInterface $expiresAt): self
    {
        return $this->state(fn (): array => [
            'expires_at' => $expiresAt,
        ]);
    }
}
