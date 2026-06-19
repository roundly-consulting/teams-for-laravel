<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Database\Factories;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;

/** @extends Factory<Member> */
final class MemberFactory extends Factory
{
    protected $model = Member::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'member_type' => 'user',
            'member_id' => fake()->numberBetween(1, 1000),
            'role' => 'user',
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
