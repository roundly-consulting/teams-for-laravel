<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;

/** @extends Factory<Invite> */
final class InviteFactory extends Factory
{
    protected $model = Invite::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'code' => Str::random(32),
            'role' => 'user',
            'meta' => [],
            'uses' => 0,
            'max_uses' => 1,
            'expires_at' => now()->addWeek(),
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function forEmail(string $email): self
    {
        return $this->state(fn (): array => [
            'email' => $email,
        ]);
    }

    public function maxUses(?int $maxUses): self
    {
        return $this->state(fn (): array => [
            'max_uses' => $maxUses,
        ]);
    }
}
