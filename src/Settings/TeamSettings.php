<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Settings;

use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Teams\Enums\JoinPolicy as JoinPolicyEnum;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Options\DefaultMemberRole;
use RoundlyConsulting\Teams\Options\JoinPolicy;
use RoundlyConsulting\Teams\Options\MaxSeats;
use RoundlyConsulting\Teams\Options\RequireApprovalToJoin;

/**
 * Typed sugar over the options-for-laravel bag for a team's first-class
 * governance settings. Unset options fall back to their declared defaults, so a
 * team that never touches its settings behaves exactly as before.
 */
final readonly class TeamSettings
{
    public function __construct(private Team $team) {}

    public function joinPolicy(): JoinPolicyEnum
    {
        /** @var JoinPolicyEnum $policy */
        $policy = Options::get(JoinPolicy::class, $this->team);

        return $policy;
    }

    public function setJoinPolicy(JoinPolicyEnum $policy): self
    {
        Options::set(JoinPolicy::class, $policy, $this->team);

        return $this;
    }

    public function maxSeats(): ?int
    {
        /** @var int|null $seats */
        $seats = Options::get(MaxSeats::class, $this->team);

        return $seats;
    }

    public function setMaxSeats(?int $seats): self
    {
        // Null reverts to the unlimited default rather than storing a null row.
        if ($seats === null) {
            Options::forget(MaxSeats::class, $this->team);

            return $this;
        }

        Options::set(MaxSeats::class, $seats, $this->team);

        return $this;
    }

    public function defaultMemberRole(): ?string
    {
        /** @var string|null $role */
        $role = Options::get(DefaultMemberRole::class, $this->team);

        return $role;
    }

    public function setDefaultMemberRole(?string $role): self
    {
        if ($role === null) {
            Options::forget(DefaultMemberRole::class, $this->team);

            return $this;
        }

        Options::set(DefaultMemberRole::class, $role, $this->team);

        return $this;
    }

    public function requireApprovalToJoin(): bool
    {
        return (bool) Options::get(RequireApprovalToJoin::class, $this->team);
    }

    public function setRequireApprovalToJoin(bool $required): self
    {
        Options::set(RequireApprovalToJoin::class, $required, $this->team);

        return $this;
    }
}
