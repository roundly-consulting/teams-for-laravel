<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Roles;

use Illuminate\Database\Eloquent\Relations\HasMany;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;

/**
 * Resolves a role key to a Role for a given team, layering optional per-team
 * overrides on top of the global role provider.
 */
final class TeamRoleResolver
{
    public function resolve(Team $team, string $key): ?Role
    {
        if ($this->perTeamEnabled()) {
            $override = $this->override($team, $key);

            if ($override !== null) {
                return $override->toRole();
            }
        }

        return Roles::find($key);
    }

    /**
     * The effective role map for a team: global roles merged with the team's
     * overrides, where an override wins on its key.
     *
     * @return array<string, Role>
     */
    public function all(Team $team): array
    {
        $roles = Roles::all();

        if (! $this->perTeamEnabled()) {
            return $roles;
        }

        foreach ($this->teamRoles($team)->get() as $override) {
            $roles[$override->key] = $override->toRole();
        }

        return $roles;
    }

    private function perTeamEnabled(): bool
    {
        return (bool) config('teams.roles.per_team', false);
    }

    private function override(Team $team, string $key): ?TeamRole
    {
        return $this->teamRoles($team)->where('key', $key)->first();
    }

    /** @return HasMany<TeamRole, Team> */
    private function teamRoles(Team $team): HasMany
    {
        return $team->teamRoles();
    }
}
