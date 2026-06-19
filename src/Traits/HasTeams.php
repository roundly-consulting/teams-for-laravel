<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Role;

trait HasTeams
{
    /** @return MorphMany<Member, $this> */
    public function teams(): MorphMany
    {
        /** @var class-string<Member> $model */
        $model = config('teams.models.member', Member::class);

        return $this->morphMany($model, 'member');
    }

    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()
            ->where('team_id', $team->getKey())
            ->exists();
    }

    public function teamRole(Team $team): ?Role
    {
        return $team->findMember($this)?->role();
    }

    public function hasTeamRole(Team $team, string $role): bool
    {
        return $team->memberHasRole($this, $role);
    }

    public function hasTeamPermission(Team $team, string $permission): bool
    {
        return $team->memberHasPermission($this, $permission);
    }
}
