<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Roles\Role;
use RoundlyConsulting\Teams\Support\MemberModel;
use RoundlyConsulting\Teams\Support\TeamModel;

trait HasTeams
{
    /** @return MorphMany<Member, $this> */
    public function teams(): MorphMany
    {
        return $this->morphMany(MemberModel::class(), 'member');
    }

    /** @return MorphMany<Team, $this> */
    public function ownedTeams(): MorphMany
    {
        return $this->morphMany(TeamModel::class(), 'owner');
    }

    /**
     * Whether a membership row exists for this model — an expired one included. For "is a
     * member right now", use isActiveMemberOf().
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()
            ->where('team_id', $team->getKey())
            ->exists();
    }

    /**
     * Same as belongsToTeam(): a membership row, an expired one included.
     */
    public function isMemberOf(Team $team): bool
    {
        return $this->belongsToTeam($team);
    }

    /**
     * Whether this model is a member of `$team` right now: a membership that has not
     * expired.
     */
    public function isActiveMemberOf(Team $team): bool
    {
        return $team->hasActiveMember($this);
    }

    public function ownsTeam(Team $team): bool
    {
        return $team->isOwnedBy($this);
    }

    public function hasTeam(): bool
    {
        return $this->teams()->exists();
    }

    /**
     * The memberships in which this model holds `$role` right now — expired
     * memberships hold no role.
     *
     * @return Collection<int, Member>
     */
    public function teamsWithRole(string $role): Collection
    {
        /** @var Collection<int, Member> $members */
        $members = $this->teams()
            ->active()
            ->where('role', $role)
            ->get();

        return $members;
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
